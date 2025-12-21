<?php
/**
 * Saved Search Data Group for Dynamic Tags
 *
 * Provides dynamic tags for saved search data in notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Saved_Search_Data_Group extends \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {

    protected static $instances = [];
    protected $saved_search;

    public function get_type(): string {
        return 'saved_search';
    }

    public static function get($saved_search = null): self {
        if (!$saved_search) {
            $saved_search = Voxel_Toolkit_Saved_Search_Model::dummy();
        }

        $id = $saved_search->get_id();
        if (!array_key_exists($id, static::$instances)) {
            static::$instances[$id] = new static($saved_search);
        }

        return static::$instances[$id];
    }

    protected function __construct($saved_search = null) {
        if (!$saved_search) {
            $this->saved_search = Voxel_Toolkit_Saved_Search_Model::dummy();
        } else {
            $this->saved_search = $saved_search;
        }
    }

    protected function properties(): array {
        return [
            'title' => \Voxel\Dynamic_Data\Tag::String('Title')->render(function() {
                $title = $this->saved_search->get_title();
                // Return title or fallback to "Saved Search" if empty
                return !empty($title) ? $title : __('Saved Search', 'voxel-toolkit');
            }),
            'created_at' => \Voxel\Dynamic_Data\Tag::String('Created At')->render(function() {
                return $this->saved_search->get_created_at();
            }),
        ];
    }
}
