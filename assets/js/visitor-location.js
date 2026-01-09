/**
 * Voxel Toolkit - Visitor Location (Browser Geolocation)
 *
 * Requests browser geolocation permission and reverse geocodes coordinates
 */

(function() {
    'use strict';

    console.log('Voxel Toolkit: Visitor Location script loaded');

    // Check if we already have location in cookie
    const existingLocation = getCookie('vt_visitor_location');
    if (existingLocation) {
        try {
            const locationData = JSON.parse(existingLocation);
            // Only use cached location if it has a city
            if (locationData && locationData.city) {
                console.log('Voxel Toolkit: Valid location already stored in cookie:', existingLocation);
                return; // Already have valid location, no need to ask again
            } else {
                console.log('Voxel Toolkit: Cookie exists but missing city, will try again');
            }
        } catch (e) {
            console.log('Voxel Toolkit: Invalid cookie data, will try again');
        }
    }

    // Check if geolocation is supported
    if (!navigator.geolocation) {
        console.log('Voxel Toolkit: Geolocation not supported by browser');
        return; // Browser doesn't support geolocation, will fall back to IP
    }

    console.log('Voxel Toolkit: Requesting geolocation permission...');

    // Request geolocation
    navigator.geolocation.getCurrentPosition(
        function(position) {
            // Success - got coordinates
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;

            console.log('Voxel Toolkit: Got coordinates:', lat, lon);

            // Reverse geocode to get city, state, country
            reverseGeocode(lat, lon);
        },
        function(error) {
            // User denied or error occurred - will fall back to IP geolocation
            console.log('Voxel Toolkit: Geolocation error:', error.code, error.message);
        },
        {
            enableHighAccuracy: false,
            timeout: 10000,
            maximumAge: 0
        }
    );

    /**
     * Reverse geocode coordinates to location data
     */
    function reverseGeocode(lat, lon) {
        console.log('Voxel Toolkit: Reverse geocoding coordinates...');

        // Use Nominatim (OpenStreetMap) free reverse geocoding API
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=10&addressdetails=1`;

        fetch(url, {
            headers: {
                'User-Agent': 'VoxelToolkit/1.0'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Voxel Toolkit: Reverse geocoding response:', data);

            if (data && data.address) {
                // Try multiple fields to find city name (Nominatim uses different fields for different places)
                const city = data.address.city
                    || data.address.town
                    || data.address.village
                    || data.address.municipality
                    || data.address.county
                    || data.address.hamlet
                    || '';

                const locationData = {
                    city: city,
                    state: data.address.state || data.address.region || '',
                    country: data.address.country || '',
                    country_code: data.address.country_code ? data.address.country_code.toUpperCase() : '',
                    latitude: lat,
                    longitude: lon
                };

                console.log('Voxel Toolkit: Parsed location data:', locationData);

                // Only save if we got a city
                if (locationData.city) {
                    // Store in cookie (expires in 1 day)
                    setCookie('vt_visitor_location', JSON.stringify(locationData), 1);

                    console.log('Voxel Toolkit: Location saved to cookie');

                    // Update dynamic tags on current page without full reload
                    updateLocationTags(locationData);
                } else {
                    console.error('Voxel Toolkit: Could not extract city from geocoding response');
                }
            } else {
                console.error('Voxel Toolkit: Invalid response from geocoding API');
            }
        })
        .catch(error => {
            console.error('Voxel Toolkit: Reverse geocoding failed:', error);
        });
    }

    /**
     * Update location tags on page without reload
     */
    function updateLocationTags(locationData) {
        // Format location strings
        const fullLocation = locationData.country_code === 'US' && locationData.state
            ? locationData.city + ', ' + locationData.state
            : locationData.city + ', ' + locationData.country;

        console.log('Voxel Toolkit: Updating location tags with:', fullLocation);

        // Find and update any elements with data-vt-location attribute
        document.querySelectorAll('[data-vt-location]').forEach(function(element) {
            const locationType = element.getAttribute('data-vt-location');
            let value = '';

            switch(locationType) {
                case 'full':
                    value = fullLocation;
                    break;
                case 'city':
                    value = locationData.city;
                    break;
                case 'state':
                    value = locationData.state;
                    break;
                case 'country':
                    value = locationData.country;
                    break;
            }

            if (value) {
                element.textContent = value;
                console.log('Voxel Toolkit: Updated element with', locationType, ':', value);
            }
        });

        // Dispatch custom event for other scripts to listen to
        const event = new CustomEvent('voxelToolkitLocationDetected', {
            detail: {
                city: locationData.city,
                state: locationData.state,
                country: locationData.country,
                country_code: locationData.country_code,
                location: fullLocation
            }
        });
        document.dispatchEvent(event);

        console.log('Voxel Toolkit: Location detected and saved. Data available for dynamic tags.');
    }

    /**
     * Set cookie
     */
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + date.toUTCString();
        document.cookie = name + '=' + value + ';' + expires + ';path=/';
    }

    /**
     * Get cookie
     */
    function getCookie(name) {
        const nameEQ = name + '=';
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
})();
