/**
 * Route Planner Widget
 *
 * Multi-provider route planner with turn-by-turn directions
 * Supports Google Maps, Mapbox, and OpenStreetMap (OSRM)
 *
 * @package Voxel_Toolkit
 */

(function($) {
    'use strict';

    /**
     * Route Planner Class
     */
    class VoxelRoutePlanner {
        constructor(wrapper, config) {
            this.$wrapper = $(wrapper);
            this.config = config;
            this.map = null;
            this.waypoints = [];
            this.route = null;
            this.routeLine = null;
            this.markers = [];
            this.popups = [];
            this.directionsService = null;
            this.directionsRenderer = null;
            this.provider = voxelRoutePlanner.mapProvider;
            this.currentTravelMode = config.travelMode || 'driving';
            this.highlightedSegment = null;
            this.initialized = false;

            this.init();
        }

        /**
         * Initialize the route planner
         */
        async init() {
            // Use Voxel's Maps.await() method which triggers lazy loading of map scripts
            if (typeof Voxel !== 'undefined' && Voxel.Maps && typeof Voxel.Maps.await === 'function') {
                // Register callback for when maps load
                Voxel.Maps.await(() => {
                    this.initMap();
                });

                // If maps already loaded, call immediately, otherwise trigger soft-load
                if (Voxel.Maps.Loaded) {
                    this.initMap();
                } else {
                    // Voxel maps are lazy loaded - we need to trigger them
                    this.waitForMaps();
                }
            } else {
                console.error('Route Planner: Voxel.Maps.await not available');
                this.showError('Voxel map library not available. Please ensure Voxel theme is active.');
            }
        }

        /**
         * Trigger soft-loaded scripts to actually load
         * Voxel uses data-src for lazy-loaded map scripts - copy to src to trigger load
         */
        triggerMapScriptLoad() {
            // Try to get handle from Voxel_Config first
            const provider = typeof Voxel_Config !== 'undefined' ? Voxel_Config.maps?.provider : null;
            if (provider && typeof Voxel_Config !== 'undefined') {
                const handle = Voxel_Config[provider]?.handle;
                if (handle) {
                    const script = document.getElementById(handle);
                    if (script && script.dataset.src) {
                        script.src = script.dataset.src;
                    }
                }
            }

            // Also find any map-related scripts with data-src
            document.querySelectorAll('script[data-src]').forEach(script => {
                const id = script.id || '';
                if (id.includes('google-maps') || id.includes('mapbox') || id.includes('openstreetmap') || id.includes('leaflet')) {
                    script.src = script.dataset.src;
                }
            });
        }

        /**
         * Poll for Voxel maps to be ready
         */
        waitForMaps() {
            // First, try to trigger the soft-loaded scripts
            this.triggerMapScriptLoad();

            let attempts = 0;
            const maxAttempts = 50; // 5 seconds max

            const checkMaps = () => {
                attempts++;

                // Need both Voxel wrapper AND underlying Google/Mapbox API to be ready
                const voxelReady = Voxel.Maps.Loaded || typeof Voxel.Maps.LatLng === 'function';
                // Google Maps needs LatLng constructor to be ready
                const googleReady = typeof google !== 'undefined' &&
                                   typeof google.maps !== 'undefined' &&
                                   typeof google.maps.LatLng === 'function';
                const mapboxReady = typeof mapboxgl !== 'undefined';
                const leafletReady = typeof L !== 'undefined';
                const providerReady = googleReady || mapboxReady || leafletReady;

                if (voxelReady && providerReady) {
                    this.initMap();
                } else if (attempts < maxAttempts) {
                    setTimeout(checkMaps, 100);
                } else {
                    console.error('Route Planner: Maps failed to load');
                    this.showError('Maps failed to load. Please refresh the page.');
                }
            };

            setTimeout(checkMaps, 100);
        }

        /**
         * Initialize the map
         */
        initMap() {
            // Prevent double initialization
            if (this.initialized) {
                return;
            }
            this.initialized = true;

            const mapEl = this.$wrapper.find('.vt-route-map').get(0);
            if (!mapEl) {
                return;
            }

            try {
                this.map = new Voxel.Maps.Map({
                    el: mapEl,
                    zoom: this.config.zoom || 12,
                    center: new Voxel.Maps.LatLng(0, 0),
                    minZoom: this.config.minZoom || 3,
                    maxZoom: this.config.maxZoom || 18,
                });

                // Initialize directions service based on provider
                this.initDirectionsService();

                // Load waypoints
                this.loadWaypoints();

                // Bind events
                this.bindEvents();
            } catch (error) {
                console.error('Route Planner: Failed to initialize map', error);
                this.showError(voxelRoutePlanner.i18n.routeFailed);
            }
        }

        /**
         * Initialize provider-specific directions service
         */
        initDirectionsService() {
            if (this.provider === 'google_maps' && typeof google !== 'undefined' && google.maps) {
                this.directionsService = new google.maps.DirectionsService();
                this.directionsRenderer = new google.maps.DirectionsRenderer({
                    suppressMarkers: true,
                    preserveViewport: true,
                    polylineOptions: {
                        strokeColor: this.config.routeLineColor,
                        strokeWeight: this.config.routeLineWeight,
                        strokeOpacity: this.config.routeLineOpacity,
                    }
                });
                this.directionsRenderer.setMap(this.map.getSourceObject());
            }
        }

        /**
         * Load waypoints via AJAX
         */
        async loadWaypoints() {
            this.showLoading(true);

            try {
                const requestData = {
                    action: 'vt_get_route_waypoints',
                    nonce: voxelRoutePlanner.nonce,
                    post_id: this.config.postId,
                    data_source: this.config.dataSource,
                    location_key: this.config.locationKey,
                };

                if (this.config.dataSource === 'repeater') {
                    requestData.repeater_key = this.config.repeaterKey;
                    requestData.label_key = this.config.labelKey || '';
                } else if (this.config.dataSource === 'post_relation') {
                    requestData.relation_key = this.config.relationKey;
                } else if (this.config.dataSource === 'post_fields') {
                    requestData.post_fields_list = this.config.postFieldsList || [];
                }

                const response = await $.ajax({
                    url: voxelRoutePlanner.ajaxUrl,
                    method: 'POST',
                    data: requestData,
                });

                if (response.success && response.data.waypoints && response.data.waypoints.length > 0) {
                    this.waypoints = response.data.waypoints;
                    this.processStartPoint();
                } else {
                    this.showEmptyState();
                }
            } catch (error) {
                this.showError(voxelRoutePlanner.i18n.routeFailed);
            }

            this.showLoading(false);
        }

        /**
         * Process start point based on configuration
         */
        processStartPoint() {
            switch (this.config.startPointMode) {
                case 'user_location':
                    this.getUserLocation();
                    break;

                case 'custom':
                    if (this.config.customStart && this.config.customStart.lat && this.config.customStart.lng) {
                        this.waypoints.unshift({
                            lat: this.config.customStart.lat,
                            lng: this.config.customStart.lng,
                            address: this.config.customStart.address || voxelRoutePlanner.i18n.start,
                            label: voxelRoutePlanner.i18n.start,
                            isStart: true,
                        });
                    }
                    this.calculateAndDisplayRoute();
                    break;

                case 'first_stop':
                default:
                    if (this.waypoints.length > 0) {
                        this.waypoints[0].isStart = true;
                    }
                    this.calculateAndDisplayRoute();
                    break;
            }
        }

        /**
         * Get user's GPS location
         */
        getUserLocation() {
            if (!navigator.geolocation) {
                this.calculateAndDisplayRoute();
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.waypoints.unshift({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        address: voxelRoutePlanner.i18n.yourLocation,
                        label: voxelRoutePlanner.i18n.yourLocation,
                        isStart: true,
                        isUserLocation: true,
                    });
                    this.calculateAndDisplayRoute();
                },
                (error) => {
                    console.warn('Route Planner: Could not get user location', error);
                    if (this.waypoints.length > 0) {
                        this.waypoints[0].isStart = true;
                    }
                    this.calculateAndDisplayRoute();
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        /**
         * Calculate and display the route
         */
        async calculateAndDisplayRoute() {
            if (this.waypoints.length < 2) {
                this.showEmptyState();
                return;
            }

            this.clearRoute();
            this.showLoading(true);

            // Apply route optimization if enabled
            let routeWaypoints = [...this.waypoints];
            if (this.config.optimizeRoute && routeWaypoints.length > 2) {
                routeWaypoints = this.optimizeWaypointOrder(routeWaypoints);
            }

            try {
                const routeResult = await this.getRouteFromProvider(routeWaypoints);
                this.displayRoute(routeResult, routeWaypoints);
            } catch (error) {
                console.error('Route calculation failed:', error);
                this.showError(voxelRoutePlanner.i18n.routeFailed);
            }

            this.showLoading(false);
        }

        /**
         * Get route from the appropriate provider
         */
        async getRouteFromProvider(waypoints) {
            const travelMode = this.currentTravelMode;

            switch (this.provider) {
                case 'google_maps':
                    return this.getGoogleRoute(waypoints, travelMode);
                case 'mapbox':
                    return this.getMapboxRoute(waypoints, travelMode);
                case 'openstreetmap':
                default:
                    return this.getOSRMRoute(waypoints, travelMode);
            }
        }

        /**
         * Get route from Google Maps Directions API
         */
        async getGoogleRoute(waypoints, travelMode) {
            return new Promise((resolve, reject) => {
                if (!this.directionsService) {
                    reject(new Error('Google Directions Service not available'));
                    return;
                }

                const origin = waypoints[0];
                const destination = waypoints[waypoints.length - 1];
                const intermediates = waypoints.slice(1, -1);

                const travelModeMap = {
                    driving: google.maps.TravelMode.DRIVING,
                    walking: google.maps.TravelMode.WALKING,
                    cycling: google.maps.TravelMode.BICYCLING,
                    transit: google.maps.TravelMode.TRANSIT,
                };

                const request = {
                    origin: new google.maps.LatLng(origin.lat, origin.lng),
                    destination: new google.maps.LatLng(destination.lat, destination.lng),
                    travelMode: travelModeMap[travelMode] || google.maps.TravelMode.DRIVING,
                    optimizeWaypoints: false,
                    waypoints: intermediates.map(wp => ({
                        location: new google.maps.LatLng(wp.lat, wp.lng),
                        stopover: true,
                    })),
                    unitSystem: this.config.distanceUnit === 'imperial'
                        ? google.maps.UnitSystem.IMPERIAL
                        : google.maps.UnitSystem.METRIC,
                };

                this.directionsService.route(request, (result, status) => {
                    if (status === 'OK') {
                        resolve(this.parseGoogleDirections(result, waypoints));
                    } else {
                        reject(new Error('Directions request failed: ' + status));
                    }
                });
            });
        }

        /**
         * Parse Google Directions result
         */
        parseGoogleDirections(result, waypoints) {
            const route = result.routes[0];
            const legs = route.legs;

            let totalDistance = 0;
            let totalDuration = 0;
            const steps = [];
            const legBounds = [];

            legs.forEach((leg, legIndex) => {
                totalDistance += leg.distance.value;
                totalDuration += leg.duration.value;

                legBounds.push({
                    start: leg.start_location,
                    end: leg.end_location,
                    legIndex,
                });

                leg.steps.forEach((step, stepIndex) => {
                    steps.push({
                        instruction: step.instructions,
                        distance: step.distance.text,
                        duration: step.duration.text,
                        maneuver: step.maneuver || '',
                        legIndex,
                        stepIndex,
                        startLocation: { lat: step.start_location.lat(), lng: step.start_location.lng() },
                        endLocation: { lat: step.end_location.lat(), lng: step.end_location.lng() },
                    });
                });
            });

            return {
                polyline: route.overview_polyline,
                distance: {
                    value: totalDistance,
                    text: this.formatDistance(totalDistance),
                },
                duration: {
                    value: totalDuration,
                    text: this.formatDuration(totalDuration),
                },
                steps,
                legBounds,
                googleResult: result,
            };
        }

        /**
         * Get route from Mapbox Directions API
         */
        async getMapboxRoute(waypoints, travelMode) {
            const profile = this.getMapboxProfile(travelMode);
            const coordinates = waypoints.map(wp => `${wp.lng},${wp.lat}`).join(';');

            const url = `https://api.mapbox.com/directions/v5/mapbox/${profile}/${coordinates}`;
            const params = {
                access_token: voxelRoutePlanner.mapboxKey,
                geometries: 'geojson',
                steps: true,
                overview: 'full',
                language: navigator.language || 'en',
            };

            const response = await $.get(url, params);

            if (response.routes && response.routes.length > 0) {
                return this.parseMapboxDirections(response.routes[0], waypoints);
            }

            throw new Error('No route found');
        }

        /**
         * Get Mapbox profile for travel mode
         */
        getMapboxProfile(travelMode) {
            const profiles = {
                driving: 'driving',
                walking: 'walking',
                cycling: 'cycling',
                transit: 'driving', // Mapbox doesn't support transit
            };
            return profiles[travelMode] || 'driving';
        }

        /**
         * Parse Mapbox Directions result
         */
        parseMapboxDirections(route, waypoints) {
            const steps = [];

            route.legs.forEach((leg, legIndex) => {
                leg.steps.forEach((step, stepIndex) => {
                    steps.push({
                        instruction: step.maneuver.instruction,
                        distance: this.formatDistance(step.distance),
                        duration: this.formatDuration(step.duration),
                        maneuver: step.maneuver.type,
                        legIndex,
                        stepIndex,
                        startLocation: { lat: step.maneuver.location[1], lng: step.maneuver.location[0] },
                    });
                });
            });

            return {
                geometry: route.geometry,
                distance: {
                    value: route.distance,
                    text: this.formatDistance(route.distance),
                },
                duration: {
                    value: route.duration,
                    text: this.formatDuration(route.duration),
                },
                steps,
            };
        }

        /**
         * Get route from OSRM (OpenStreetMap)
         */
        async getOSRMRoute(waypoints, travelMode) {
            const profile = this.getOSRMProfile(travelMode);
            const coordinates = waypoints.map(wp => `${wp.lng},${wp.lat}`).join(';');

            const url = `https://router.project-osrm.org/route/v1/${profile}/${coordinates}`;
            const params = {
                overview: 'full',
                geometries: 'geojson',
                steps: true,
            };

            const response = await $.get(url, params);

            if (response.routes && response.routes.length > 0) {
                return this.parseOSRMDirections(response.routes[0], waypoints);
            }

            throw new Error('No route found');
        }

        /**
         * Get OSRM profile for travel mode
         */
        getOSRMProfile(travelMode) {
            const profiles = {
                driving: 'car',
                walking: 'foot',
                cycling: 'bike',
                transit: 'car',
            };
            return profiles[travelMode] || 'car';
        }

        /**
         * Parse OSRM Directions result
         */
        parseOSRMDirections(route, waypoints) {
            const steps = [];

            route.legs.forEach((leg, legIndex) => {
                leg.steps.forEach((step, stepIndex) => {
                    const instruction = step.maneuver.instruction ||
                        (step.name ? `Continue on ${step.name}` : 'Continue');

                    steps.push({
                        instruction: instruction,
                        distance: this.formatDistance(step.distance),
                        duration: this.formatDuration(step.duration),
                        maneuver: step.maneuver.type,
                        legIndex,
                        stepIndex,
                        startLocation: {
                            lat: step.maneuver.location[1],
                            lng: step.maneuver.location[0]
                        },
                    });
                });
            });

            return {
                geometry: route.geometry,
                distance: {
                    value: route.distance,
                    text: this.formatDistance(route.distance),
                },
                duration: {
                    value: route.duration,
                    text: this.formatDuration(route.duration),
                },
                steps,
            };
        }

        /**
         * Display the calculated route
         */
        displayRoute(routeResult, waypoints) {
            // Display route line
            if (this.provider === 'google_maps' && routeResult.googleResult) {
                this.directionsRenderer.setDirections(routeResult.googleResult);
            } else {
                this.drawRouteLine(routeResult.geometry);
            }

            // Add markers
            this.addWaypointMarkers(waypoints);

            // Fit map to route bounds
            this.fitMapToRoute(waypoints);

            // Update summary
            this.updateRouteSummary(routeResult);

            // Update directions panel
            if (this.config.showDirections) {
                this.renderDirectionsPanel(waypoints, routeResult.steps);
            }
        }

        /**
         * Draw route line for Mapbox/OSM
         */
        drawRouteLine(geometry) {
            if (this.provider === 'mapbox') {
                const sourceMap = this.map.getSourceObject();

                if (sourceMap.getSource('vt-route')) {
                    sourceMap.getSource('vt-route').setData(geometry);
                } else {
                    sourceMap.addSource('vt-route', {
                        type: 'geojson',
                        data: geometry,
                    });

                    sourceMap.addLayer({
                        id: 'vt-route',
                        type: 'line',
                        source: 'vt-route',
                        layout: {
                            'line-join': 'round',
                            'line-cap': 'round',
                        },
                        paint: {
                            'line-color': this.config.routeLineColor,
                            'line-width': this.config.routeLineWeight,
                            'line-opacity': this.config.routeLineOpacity,
                        },
                    });
                }
            } else if (this.provider === 'openstreetmap') {
                if (this.routeLine) {
                    this.routeLine.remove();
                }

                // Get the Leaflet map instance
                const leafletMap = this.map.getSourceObject();

                // Create a proper GeoJSON Feature for the route
                const routeGeoJSON = {
                    type: 'Feature',
                    properties: {},
                    geometry: geometry
                };

                // Use L.geoJSON with explicit line styling (no fill)
                this.routeLine = L.geoJSON(routeGeoJSON, {
                    style: {
                        color: this.config.routeLineColor || '#4285F4',
                        weight: this.config.routeLineWeight || 4,
                        opacity: this.config.routeLineOpacity || 0.8,
                        fill: false,
                        fillOpacity: 0,
                        stroke: true,
                        lineCap: 'round',
                        lineJoin: 'round'
                    }
                }).addTo(leafletMap);
            }
        }

        /**
         * Add waypoint markers to the map
         */
        addWaypointMarkers(waypoints) {
            this.clearMarkers();

            waypoints.forEach((wp, index) => {
                const label = this.getMarkerLabel(index, waypoints.length);
                const color = this.getMarkerColor(index, waypoints.length);

                const markerTemplate = this.createMarkerTemplate(label, color);

                const position = new Voxel.Maps.LatLng(wp.lat, wp.lng);

                const marker = new Voxel.Maps.Marker({
                    map: this.map,
                    position: position,
                    template: markerTemplate,
                });

                // Create popup for marker
                this.createMarkerPopup(marker, wp, index, waypoints.length);

                this.markers.push(marker);
            });
        }

        /**
         * Create popup for a marker
         */
        createMarkerPopup(marker, waypoint, index, total) {
            const popupContent = `
                <div class="vt-route-popup">
                    <div class="vt-popup-title">${this.escapeHtml(waypoint.label)}</div>
                    ${waypoint.address ? `<div class="vt-popup-address">${this.escapeHtml(waypoint.address)}</div>` : ''}
                    ${waypoint.permalink ? `<a href="${waypoint.permalink}" class="vt-popup-link" target="_blank">${voxelRoutePlanner.i18n.view}</a>` : ''}
                </div>
            `;

            const popup = new Voxel.Maps.Popup({
                map: this.map,
                position: marker.getPosition(),
                content: popupContent,
            });

            marker.onClick = () => {
                // Close other popups
                this.popups.forEach(p => p.hide());
                popup.show();
            };

            this.popups.push(popup);
        }

        /**
         * Get marker label based on style
         */
        getMarkerLabel(index, total) {
            if (this.config.markerStyle === 'numbered') {
                return (index + 1).toString();
            } else if (this.config.markerStyle === 'lettered') {
                return String.fromCharCode(65 + (index % 26));
            }
            return '';
        }

        /**
         * Get marker color based on position
         */
        getMarkerColor(index, total) {
            if (index === 0) {
                return this.config.startMarkerColor || '#22c55e';
            } else if (index === total - 1) {
                return this.config.endMarkerColor || '#ef4444';
            }
            return this.config.waypointMarkerColor || '#3b82f6';
        }

        /**
         * Create marker HTML template
         */
        createMarkerTemplate(label, color) {
            return `
                <div class="vt-route-marker" style="background-color: ${color}">
                    <span class="vt-route-marker-label">${label}</span>
                </div>
            `;
        }

        /**
         * Render the directions panel
         */
        renderDirectionsPanel(waypoints, steps) {
            const $panel = this.$wrapper.find('.vt-route-directions-panel');
            const $waypointsList = $panel.find('.vt-route-waypoints-list');
            const $stepsList = $panel.find('.vt-route-steps');

            // Render waypoints list
            let waypointsHtml = `<ul class="vt-waypoints" ${this.config.allowReorder ? 'data-sortable="true"' : ''}>`;
            waypoints.forEach((wp, index) => {
                const markerLabel = this.getMarkerLabel(index, waypoints.length);
                const color = this.getMarkerColor(index, waypoints.length);
                const displayLabel = wp.label || wp.address;
                // Show address below label if enabled and address is different from label
                const showAddress = this.config.showWaypointAddress && wp.address && wp.label && wp.address !== wp.label;

                waypointsHtml += `
                    <li class="vt-waypoint-item" data-index="${index}" ${this.config.allowReorder ? 'draggable="true"' : ''}>
                        <span class="vt-waypoint-marker" style="background-color: ${color}">${markerLabel}</span>
                        <div class="vt-waypoint-info">
                            <span class="vt-waypoint-label">${this.escapeHtml(displayLabel)}</span>
                            ${showAddress ? `<span class="vt-waypoint-address">${this.escapeHtml(wp.address)}</span>` : ''}
                        </div>
                        ${wp.permalink ? `<a href="${wp.permalink}" class="vt-waypoint-link" target="_blank">${voxelRoutePlanner.i18n.view}</a>` : ''}
                    </li>
                `;
            });
            waypointsHtml += '</ul>';
            $waypointsList.html(waypointsHtml);

            // Render turn-by-turn directions (if enabled)
            if (this.config.showTurnByTurn !== false) {
                let stepsHtml = '<ol class="vt-direction-steps">';
                steps.forEach((step, index) => {
                    const maneuverIcon = this.getManeuverIcon(step.maneuver);
                    stepsHtml += `
                        <li class="vt-direction-step" data-step-index="${index}" data-lat="${step.startLocation?.lat || ''}" data-lng="${step.startLocation?.lng || ''}">
                            ${maneuverIcon ? `<span class="vt-step-icon">${maneuverIcon}</span>` : ''}
                            <div class="vt-step-content">
                                <span class="vt-step-instruction">${step.instruction}</span>
                                <span class="vt-step-meta">
                                    <span class="vt-step-distance">${step.distance}</span>
                                    <span class="vt-step-duration">${step.duration}</span>
                                </span>
                            </div>
                        </li>
                    `;
                });
                stepsHtml += '</ol>';
                $stepsList.html(stepsHtml);

                // Setup step click handlers
                this.setupStepHighlighting();
            } else {
                // Hide the steps container when turn-by-turn is disabled
                $stepsList.hide();
            }

            // Setup drag and drop if enabled
            if (this.config.allowReorder) {
                this.setupDragReorder();
            }
        }

        /**
         * Get icon for maneuver type
         */
        getManeuverIcon(maneuver) {
            const icons = {
                'turn-left': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 14-4-4 4-4"/><path d="M5 10h11a4 4 0 0 1 4 4v7"/></svg>',
                'turn-right': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 14 4-4-4-4"/><path d="M19 10H8a4 4 0 0 0-4 4v7"/></svg>',
                'straight': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>',
                'arrive': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>',
            };
            return icons[maneuver] || '';
        }

        /**
         * Setup step click to highlight on map
         */
        setupStepHighlighting() {
            const self = this;
            this.$wrapper.find('.vt-direction-step').on('click', function() {
                const lat = parseFloat($(this).data('lat'));
                const lng = parseFloat($(this).data('lng'));

                if (lat && lng) {
                    self.map.panTo(new Voxel.Maps.LatLng(lat, lng));
                    self.map.setZoom(16);

                    // Highlight this step
                    self.$wrapper.find('.vt-direction-step').removeClass('active');
                    $(this).addClass('active');
                }
            });
        }

        /**
         * Setup drag and drop reordering
         */
        setupDragReorder() {
            const $list = this.$wrapper.find('.vt-waypoints');
            let draggedItem = null;

            $list.on('dragstart', '.vt-waypoint-item', (e) => {
                draggedItem = e.currentTarget;
                $(draggedItem).addClass('dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
            });

            $list.on('dragend', '.vt-waypoint-item', (e) => {
                $(draggedItem).removeClass('dragging');
                draggedItem = null;
            });

            $list.on('dragover', '.vt-waypoint-item', (e) => {
                e.preventDefault();
                const targetItem = e.currentTarget;
                if (targetItem !== draggedItem) {
                    const rect = targetItem.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (e.clientY < midY) {
                        $(targetItem).before(draggedItem);
                    } else {
                        $(targetItem).after(draggedItem);
                    }
                }
            });

            $list.on('drop', (e) => {
                e.preventDefault();
                // Reorder waypoints array based on new DOM order
                const newOrder = [];
                $list.find('.vt-waypoint-item').each((i, el) => {
                    const index = parseInt($(el).data('index'), 10);
                    newOrder.push(this.waypoints[index]);
                });
                this.waypoints = newOrder;
                // Recalculate route
                this.calculateAndDisplayRoute();
            });
        }

        /**
         * Update route summary display
         */
        updateRouteSummary(routeResult) {
            if (this.config.showDistance) {
                this.$wrapper.find('.vt-distance-value').text(routeResult.distance.text);
            }
            if (this.config.showDuration) {
                this.$wrapper.find('.vt-duration-value').text(routeResult.duration.text);
            }
        }

        /**
         * Optimize waypoint order using nearest neighbor algorithm
         */
        optimizeWaypointOrder(waypoints) {
            if (waypoints.length <= 2) return waypoints;

            const optimized = [waypoints[0]]; // Keep start fixed
            const remaining = waypoints.slice(1);

            while (remaining.length > 0) {
                const last = optimized[optimized.length - 1];
                let nearestIndex = 0;
                let nearestDist = this.haversineDistance(last, remaining[0]);

                for (let i = 1; i < remaining.length; i++) {
                    const dist = this.haversineDistance(last, remaining[i]);
                    if (dist < nearestDist) {
                        nearestDist = dist;
                        nearestIndex = i;
                    }
                }

                optimized.push(remaining.splice(nearestIndex, 1)[0]);
            }

            return optimized;
        }

        /**
         * Calculate haversine distance between two points
         */
        haversineDistance(p1, p2) {
            const R = 6371; // Earth radius in km
            const dLat = this.toRad(p2.lat - p1.lat);
            const dLon = this.toRad(p2.lng - p1.lng);
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(this.toRad(p1.lat)) * Math.cos(this.toRad(p2.lat)) *
                      Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        /**
         * Convert degrees to radians
         */
        toRad(deg) {
            return deg * (Math.PI / 180);
        }

        /**
         * Format distance for display
         */
        formatDistance(meters) {
            if (this.config.distanceUnit === 'imperial') {
                const miles = meters * 0.000621371;
                return miles < 0.1
                    ? Math.round(meters * 3.28084) + ' ft'
                    : miles.toFixed(1) + ' mi';
            }
            return meters < 1000
                ? Math.round(meters) + ' m'
                : (meters / 1000).toFixed(1) + ' km';
        }

        /**
         * Format duration for display
         */
        formatDuration(seconds) {
            const hours = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);

            if (hours > 0) {
                return `${hours} hr ${mins} min`;
            }
            return `${mins} min`;
        }

        /**
         * Fit map to show all waypoints
         */
        fitMapToRoute(waypoints) {
            if (waypoints.length === 0) return;

            // Calculate min/max lat/lng to create bounds
            let minLat = waypoints[0].lat;
            let maxLat = waypoints[0].lat;
            let minLng = waypoints[0].lng;
            let maxLng = waypoints[0].lng;

            waypoints.forEach(wp => {
                if (wp.lat < minLat) minLat = wp.lat;
                if (wp.lat > maxLat) maxLat = wp.lat;
                if (wp.lng < minLng) minLng = wp.lng;
                if (wp.lng > maxLng) maxLng = wp.lng;
            });

            // Add padding
            const latPadding = (maxLat - minLat) * 0.1 || 0.01;
            const lngPadding = (maxLng - minLng) * 0.1 || 0.01;

            const southwest = new Voxel.Maps.LatLng(minLat - latPadding, minLng - lngPadding);
            const northeast = new Voxel.Maps.LatLng(maxLat + latPadding, maxLng + lngPadding);
            const bounds = new Voxel.Maps.Bounds(southwest, northeast);

            this.map.fitBounds(bounds);
        }

        /**
         * Clear existing route display
         */
        clearRoute() {
            this.clearMarkers();
            this.clearPopups();

            if (this.provider === 'google_maps' && this.directionsRenderer) {
                this.directionsRenderer.setDirections({ routes: [] });
            }

            if (this.routeLine) {
                this.routeLine.remove();
                this.routeLine = null;
            }

            if (this.provider === 'mapbox') {
                const sourceMap = this.map.getSourceObject();
                if (sourceMap.getLayer('vt-route')) {
                    sourceMap.removeLayer('vt-route');
                }
                if (sourceMap.getSource('vt-route')) {
                    sourceMap.removeSource('vt-route');
                }
            }
        }

        /**
         * Clear all markers
         */
        clearMarkers() {
            this.markers.forEach(m => {
                if (m.remove) m.remove();
            });
            this.markers = [];
        }

        /**
         * Clear all popups
         */
        clearPopups() {
            this.popups.forEach(p => {
                if (p.remove) p.remove();
            });
            this.popups = [];
        }

        /**
         * Bind widget events
         */
        bindEvents() {
            // Travel mode buttons
            this.$wrapper.on('click', '.vt-travel-mode-btn', (e) => {
                const $btn = $(e.currentTarget);
                const mode = $btn.data('mode');

                this.$wrapper.find('.vt-travel-mode-btn').removeClass('active');
                $btn.addClass('active');

                this.currentTravelMode = mode;
                this.calculateAndDisplayRoute();
            });

            // Waypoint item click to pan to marker
            this.$wrapper.on('click', '.vt-waypoint-item', (e) => {
                const index = $(e.currentTarget).data('index');
                if (this.markers[index]) {
                    const position = this.markers[index].getPosition();
                    this.map.panTo(position);
                    this.map.setZoom(15);
                }
            });

            // Export button: Google Maps
            this.$wrapper.on('click', '.vt-export-google-maps', (e) => {
                e.preventDefault();
                const url = this.generateGoogleMapsUrl();
                this.openExternalUrl(url);
            });

            // Export button: Apple Maps
            this.$wrapper.on('click', '.vt-export-apple-maps', (e) => {
                e.preventDefault();
                const url = this.generateAppleMapsUrl();
                this.openExternalUrl(url);
            });

            // Export button: Download GPX
            this.$wrapper.on('click', '.vt-export-gpx', (e) => {
                e.preventDefault();
                this.downloadGPX();
            });
        }

        /**
         * Show loading state
         */
        showLoading(show) {
            this.$wrapper.find('.vt-route-loading').toggle(show);
        }

        /**
         * Show empty state
         */
        showEmptyState() {
            this.$wrapper.find('.vt-route-content').html(
                `<div class="vt-route-empty">${voxelRoutePlanner.i18n.noWaypoints}</div>`
            );
        }

        /**
         * Show error state
         */
        showError(message) {
            this.$wrapper.find('.vt-route-content').html(
                `<div class="vt-route-error">${message || voxelRoutePlanner.i18n.routeFailed}</div>`
            );
        }

        /**
         * Escape HTML for safe display
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        /**
         * Generate Google Maps directions URL
         */
        generateGoogleMapsUrl() {
            if (this.waypoints.length < 2) return null;

            const origin = `${this.waypoints[0].lat},${this.waypoints[0].lng}`;
            const destination = `${this.waypoints[this.waypoints.length - 1].lat},${this.waypoints[this.waypoints.length - 1].lng}`;

            // Google Maps travel mode mapping
            const travelModeMap = {
                driving: 'driving',
                walking: 'walking',
                cycling: 'bicycling',
                transit: 'transit',
            };
            const mode = travelModeMap[this.currentTravelMode] || 'driving';

            let url = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(origin)}&destination=${encodeURIComponent(destination)}&travelmode=${mode}`;

            // Add intermediate waypoints if any
            if (this.waypoints.length > 2) {
                const intermediate = this.waypoints
                    .slice(1, -1)
                    .map(wp => `${wp.lat},${wp.lng}`)
                    .join('|');
                url += `&waypoints=${encodeURIComponent(intermediate)}`;
            }

            return url;
        }

        /**
         * Generate Apple Maps directions URL
         */
        generateAppleMapsUrl() {
            if (this.waypoints.length < 2) return null;

            const start = `${this.waypoints[0].lat},${this.waypoints[0].lng}`;
            const end = `${this.waypoints[this.waypoints.length - 1].lat},${this.waypoints[this.waypoints.length - 1].lng}`;

            // Apple Maps direction flag: d=driving, w=walking, r=transit
            const dirFlagMap = {
                driving: 'd',
                walking: 'w',
                cycling: 'w', // Apple Maps doesn't have cycling, use walking
                transit: 'r',
            };
            const dirFlag = dirFlagMap[this.currentTravelMode] || 'd';

            return `https://maps.apple.com/?saddr=${encodeURIComponent(start)}&daddr=${encodeURIComponent(end)}&dirflg=${dirFlag}`;
        }

        /**
         * Generate GPX file content
         */
        generateGPX() {
            const now = new Date().toISOString();
            const filename = this.config.gpxFilename || 'route';

            let gpx = `<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Voxel Toolkit" xmlns="http://www.topografix.com/GPX/1/1">
  <metadata>
    <name>${this.escapeXml(filename)}</name>
    <time>${now}</time>
  </metadata>
`;

            this.waypoints.forEach((wp, i) => {
                const label = wp.label || wp.address || `Waypoint ${i + 1}`;
                gpx += `  <wpt lat="${wp.lat}" lon="${wp.lng}">
    <name>${this.escapeXml(label)}</name>
  </wpt>
`;
            });

            gpx += `</gpx>`;
            return gpx;
        }

        /**
         * Download GPX file
         */
        downloadGPX() {
            const gpx = this.generateGPX();
            const blob = new Blob([gpx], { type: 'application/gpx+xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const filename = (this.config.gpxFilename || 'route') + '.gpx';
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        /**
         * Escape XML special characters
         */
        escapeXml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&apos;');
        }

        /**
         * Open URL in new tab
         */
        openExternalUrl(url) {
            if (url) {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        }
    }

    /**
     * Initialize all route planners on page
     */
    function initRoutePlanners() {
        $('.vt-route-planner-wrapper').each(function() {
            if (this.__vt_route_planner__) return;

            const config = $(this).data('config');
            if (config) {
                this.__vt_route_planner__ = new VoxelRoutePlanner(this, config);
            }
        });
    }

    // Initialize when maps are loaded
    $(document).on('maps:loaded', initRoutePlanners);

    // Also check on DOM ready in case maps already loaded
    $(document).ready(function() {
        if (typeof Voxel !== 'undefined' && Voxel.Maps && Voxel.Maps.Loaded) {
            initRoutePlanners();
        }
    });

    // Re-initialize on Elementor frontend init (for editor preview)
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/voxel-toolkit-route-planner.default', function($scope) {
                initRoutePlanners();
            });
        }
    });

})(jQuery);
