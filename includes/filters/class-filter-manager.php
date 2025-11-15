<?php
/**
 * Filter Manager
 *
 * Registers custom filter types with Voxel
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Filter_Manager {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Filter is registered at top level in this file
        // Load template and register Vue component
        add_action('wp_footer', array($this, 'load_template'));
        add_action('wp_footer', array($this, 'register_vue_component'));
    }

    /**
     * Load the Vue templates for custom filters
     */
    public function load_template() {
        // Load membership plan filter template
        $membership_template = VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/membership-plan-filter.php';
        if (file_exists($membership_template)) {
            require $membership_template;
        }

        // Load user role filter template
        $role_template = VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/user-role-filter.php';
        if (file_exists($role_template)) {
            require $role_template;
        }
    }

    /**
     * Register Vue.js component for the membership plan filter
     */
    public function register_vue_component() {
        ?>
        <script>
        document.addEventListener('voxel/search-form/init', e => {
            const { app, config, el } = e.detail;

            // Register membership plan filter component (clone of terms filter)
            app.component('filter-membership-plan', {
                template: '#sf-membership-plan-filter',
                name: 'membership-plan-filter',
                props: { filter: Object, repeaterId: String },
                data() {
                    return {
                        value: this.filter.props.selected || {},
                        search: '',
                        firstLabel: '',
                        remainingCount: 0
                    };
                },
                created() {
                    this.firstLabel = this._getFirstLabel();
                    this.remainingCount = this._getRemainingCount();
                },
                methods: {
                    saveValue() {
                        const newValue = this.isFilled() ? Object.keys(this.value).join(',') : null;
                        console.log('Membership Plan Filter - saveValue()');
                        console.log('  this.value:', this.value);
                        console.log('  Keys:', Object.keys(this.value));
                        console.log('  New filter value:', newValue);
                        this.filter.value = newValue;
                        this.firstLabel = this._getFirstLabel();
                        this.remainingCount = this._getRemainingCount();
                    },
                    onSave() {
                        console.log('Membership Plan Filter - onSave() called');
                        this.saveValue();
                        console.log('After saveValue, filter.value:', this.filter.value);
                        this.$refs.formGroup?.blur();
                    },
                    onBlur() {
                        console.log('Membership Plan Filter - onBlur() called');
                        this.saveValue();
                    },
                    onClear() {
                        this.value = {};
                        this.search = '';
                        this.$refs.searchInput?.focus();
                    },
                    isFilled() {
                        return Object.keys(this.value).length > 0;
                    },
                    _getFirstLabel() {
                        return Object.values(this.value)[0]?.label || '';
                    },
                    _getRemainingCount() {
                        return Object.values(this.value).length - 1;
                    },
                    selectPlan(plan) {
                        if (this.value[plan.key]) {
                            delete this.value[plan.key];
                        } else {
                            this.value[plan.key] = plan;
                        }

                        // For buttons display, save and submit immediately
                        if (this.filter.props.display_as === 'buttons') {
                            this.saveValue();
                        }
                    },
                    onReset() {
                        this.search = '';
                        this.value = {};
                        this.saveValue();
                    }
                },
                computed: {
                    filteredPlans() {
                        const plans = Object.values(this.filter.props.choices);
                        if (!this.search.trim().length) {
                            return plans;
                        }
                        const searchTerm = this.search.trim().toLowerCase();
                        return plans.filter(plan =>
                            plan.label.toLowerCase().includes(searchTerm)
                        );
                    },
                    isPending() {
                        return false;
                    }
                }
            });

            // Register user role filter component (clone of membership plan filter)
            app.component('filter-user-role', {
                template: '#sf-user-role-filter',
                name: 'user-role-filter',
                props: { filter: Object, repeaterId: String },
                data() {
                    return {
                        value: this.filter.props.selected || {},
                        search: '',
                        firstLabel: '',
                        remainingCount: 0
                    };
                },
                created() {
                    this.firstLabel = this._getFirstLabel();
                    this.remainingCount = this._getRemainingCount();
                },
                methods: {
                    saveValue() {
                        const newValue = this.isFilled() ? Object.keys(this.value).join(',') : null;
                        this.filter.value = newValue;
                        this.firstLabel = this._getFirstLabel();
                        this.remainingCount = this._getRemainingCount();
                    },
                    onSave() {
                        this.saveValue();
                        this.$refs.formGroup?.blur();
                    },
                    onBlur() {
                        this.saveValue();
                    },
                    onClear() {
                        this.value = {};
                        this.search = '';
                        this.$refs.searchInput?.focus();
                    },
                    isFilled() {
                        return Object.keys(this.value).length > 0;
                    },
                    _getFirstLabel() {
                        return Object.values(this.value)[0]?.label || '';
                    },
                    _getRemainingCount() {
                        return Object.values(this.value).length - 1;
                    },
                    selectRole(role) {
                        if (this.value[role.key]) {
                            delete this.value[role.key];
                        } else {
                            this.value[role.key] = role;
                        }

                        // For buttons display, save and submit immediately
                        if (this.filter.props.display_as === 'buttons') {
                            this.saveValue();
                        }
                    },
                    onReset() {
                        this.search = '';
                        this.value = {};
                        this.saveValue();
                    }
                },
                computed: {
                    filteredRoles() {
                        const roles = Object.values(this.filter.props.choices);
                        if (!this.search.trim().length) {
                            return roles;
                        }
                        const searchTerm = this.search.trim().toLowerCase();
                        return roles.filter(role =>
                            role.label.toLowerCase().includes(searchTerm)
                        );
                    },
                    isPending() {
                        return false;
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Add membership plan filter to Voxel's filter types
     *
     * @param array $filter_types Existing filter types
     * @return array Modified filter types
     */
    public function add_membership_plan_filter($filter_types) {
        $filter_types['membership-plan'] = \Voxel_Toolkit\Filters\Membership_Plan_Filter::class;
        return $filter_types;
    }
}

// Register filters at the top level (before any theme hooks)
// Load the class files INSIDE the filter callback to ensure Voxel classes exist first
add_filter('voxel/filter-types', function($filter_types) {
    // Load membership plan filter class NOW (when filter is called, Voxel classes exist)
    if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/filters/class-membership-plan-filter.php')) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/filters/class-membership-plan-filter.php';
    }

    // Load user role filter class
    if (file_exists(VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/filters/class-user-role-filter.php')) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/filters/class-user-role-filter.php';
    }

    $filter_types['membership-plan'] = \Voxel_Toolkit\Filters\Membership_Plan_Filter::class;
    $filter_types['user-role'] = \Voxel_Toolkit\Filters\User_Role_Filter::class;

    return $filter_types;
});

// Initialize the manager (for any future functionality)
Voxel_Toolkit_Filter_Manager::instance();
