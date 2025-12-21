<?php
/**
 * Saved Search Event Class
 *
 * App event for notifying users when new posts match their saved searches.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Saved_Search_Event extends \Voxel\Events\Base_Event {

    public $post_type;
    public $post;
    public $author;
    public $recipient;
    public $saved_search;

    public function __construct(\Voxel\Post_Type $post_type) {
        $this->post_type = $post_type;
    }

    /**
     * Prepare event data
     */
    public function prepare($post_id, $recipient_id, $saved_search_id = null) {
        $post = \Voxel\Post::force_get($post_id);
        if (!$post) {
            throw new \Exception('Post not found.');
        }

        if (!$recipient_id) {
            throw new \Exception('Recipient not specified.');
        }

        $recipient = \Voxel\User::get($recipient_id);
        if (!$recipient) {
            throw new \Exception('Recipient user not found.');
        }

        $this->recipient = $recipient;
        $this->post = $post;
        $this->author = $post->get_author();

        // Load saved search if ID provided
        if ($saved_search_id) {
            $this->saved_search = Voxel_Toolkit_Saved_Search_Model::get($saved_search_id);
        }
    }

    /**
     * Get event key
     */
    public function get_key(): string {
        return sprintf('post-types/%s/vt-saved-search:post-published', $this->post_type->get_key());
    }

    /**
     * Get event label
     */
    public function get_label(): string {
        return sprintf('%s: New post matches saved search (VT)', $this->post_type->get_label());
    }

    /**
     * Get event category
     */
    public function get_category() {
        return sprintf('post-type:%s', $this->post_type->get_key());
    }

    /**
     * Define notifications
     */
    public static function notifications(): array {
        return [
            'notify-subscriber' => [
                'label' => 'Notify subscriber',
                'recipient' => function($event) {
                    return $event->recipient;
                },
                'inapp' => [
                    'enabled' => true,
                    'subject' => "A new @post(post_type:singular) matches your saved search: @post(title)",
                    'details' => function($event) {
                        return [
                            'post_id' => $event->post->get_id(),
                            'saved_search_id' => $event->saved_search ? $event->saved_search->get_id() : null,
                        ];
                    },
                    'apply_details' => function($event, $details) {
                        $event->prepare(
                            $details['post_id'] ?? null,
                            $event->recipient->get_id(),
                            $details['saved_search_id'] ?? null
                        );
                    },
                    'links_to' => function($event) {
                        return $event->post ? $event->post->get_link() : null;
                    },
                    'image_id' => function($event) {
                        return $event->post ? $event->post->get_logo_id() : null;
                    },
                ],
                'email' => [
                    'enabled' => true,
                    'subject' => "New @post(post_type:singular) matches your saved search",
                    'message' => <<<HTML
<p>Hi @recipient(display_name),</p>
<p>A new @post(post_type:singular) named <strong>@post(title)</strong> has been posted that matches your saved search "<strong>@saved_search(title)</strong>".</p>
<p><a href="@post(url)">View @post(post_type:singular)</a></p>
HTML,
                ],
                'sms' => [
                    'enabled' => false,
                    'message' => "New @post(post_type:singular) \"@post(title)\" matches your saved search. View: @post(url)",
                ],
            ],
        ];
    }

    /**
     * Set mock properties for preview
     */
    public function set_mock_props() {
        $this->author = \Voxel\User::mock();
        $this->saved_search = Voxel_Toolkit_Saved_Search_Model::dummy();
    }

    /**
     * Get dynamic tags
     */
    public function dynamic_tags(): array {
        // Load data group class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/saved-search/class-saved-search-data-group.php';

        return [
            'author' => \Voxel\Dynamic_Data\Group::User($this->author),
            'post' => \Voxel\Dynamic_Data\Group::Post(
                $this->post ?: \Voxel\Post::mock(['post_type' => $this->post_type->get_key()])
            ),
            'saved_search' => Voxel_Toolkit_Saved_Search_Data_Group::get(
                $this->saved_search ?? Voxel_Toolkit_Saved_Search_Model::dummy()
            ),
        ];
    }
}
