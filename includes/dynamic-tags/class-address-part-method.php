<?php
/**
 * Address Part Method
 *
 * Extract specific parts from a Voxel address field
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Address Part Method
 *
 * Extracts specific components from Voxel address fields.
 * Works with international addresses.
 */
class Voxel_Toolkit_Address_Part_Method extends \Voxel\Dynamic_Data\Modifiers\Group_Methods\Base_Group_Method {

    /**
     * Get method label
     */
    public function get_label(): string {
        return 'Address part';
    }

    /**
     * Get method key
     */
    public function get_key(): string {
        return 'address_part';
    }

    /**
     * Define method arguments
     */
    protected function define_args(): void {
        $this->define_arg([
            'type' => 'select',
            'label' => 'Address component',
            'description' => 'Select which part of the address to extract',
            'options' => [
                'number' => 'Street Number',
                'street' => 'Street Name',
                'city' => 'City',
                'state' => 'State/Province',
                'postal_code' => 'Postal Code/ZIP',
                'country' => 'Country',
            ],
        ]);
    }

    /**
     * Run the method
     */
    public function run($group) {
        error_log('Address_Part Method: run() called');
        error_log('Address_Part Method: group type = ' . get_class($group));
        error_log('Address_Part Method: group dump keys = ' . implode(', ', array_keys(get_object_vars($group))));

        // Get the part to extract from arguments
        $part = isset($this->args[0]) ? $this->args[0] : 'city';
        if (is_array($part) && isset($part['content'])) {
            $part = $part['content'];
        }

        error_log('Address_Part Method: part = ' . $part);

        // Try to get the value directly from the group
        $value = null;

        // Check if this is a property group with a value
        if (isset($group->_property)) {
            error_log('Address_Part Method: group has _property');
            $property = $group->_property;
            if (method_exists($property, 'get_value')) {
                $value = $property->get_value();
                error_log('Address_Part Method: got value from property->get_value()');
            }
        }

        // Check if group has a get_value method
        if ($value === null && method_exists($group, 'get_value')) {
            $value = $group->get_value();
            error_log('Address_Part Method: got value from group->get_value()');
        }

        error_log('Address_Part Method: value = ' . print_r($value, true));

        if (!is_array($value)) {
            error_log('Address_Part Method: Value is not array');
            return '';
        }

        // Extract the requested part
        $result = $this->extract_address_part($value, $part);
        error_log('Address_Part Method: result = ' . $result);
        return $result;
    }

    /**
     * Extract specific part from address data
     *
     * @param array $address_data Voxel address data
     * @param string $part Which part to extract
     * @return string Extracted address component
     */
    private function extract_address_part($address_data, $part) {
        switch ($part) {
            case 'number':
                return $this->get_component($address_data, 'street_number');

            case 'street':
                return $this->get_component($address_data, 'route');

            case 'city':
                // Try locality first, then postal_town, then administrative_area_level_2
                $city = $this->get_component($address_data, 'locality');
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'postal_town');
                }
                if (empty($city)) {
                    $city = $this->get_component($address_data, 'administrative_area_level_2');
                }
                return $city;

            case 'state':
                // Try administrative_area_level_1 (works for most countries)
                return $this->get_component($address_data, 'administrative_area_level_1');

            case 'postal_code':
                return $this->get_component($address_data, 'postal_code');

            case 'country':
                return $this->get_component($address_data, 'country');

            default:
                return '';
        }
    }

    /**
     * Get component from address data
     *
     * @param array $address_data Address data array
     * @param string $component Component type to extract
     * @return string Component value
     */
    private function get_component($address_data, $component) {
        // Check if we have the raw Google Places format
        if (isset($address_data['address_components']) && is_array($address_data['address_components'])) {
            foreach ($address_data['address_components'] as $comp) {
                if (isset($comp['types']) && in_array($component, $comp['types'])) {
                    // Return long_name for better readability
                    return isset($comp['long_name']) ? $comp['long_name'] : '';
                }
            }
        }

        // Check if Voxel has already parsed it into a simpler format
        if (isset($address_data[$component])) {
            return $address_data[$component];
        }

        return '';
    }
}
