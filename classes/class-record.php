<?php
/** MainWP Child Reports record. */

namespace WP_MainWP_Stream;

/**
 * Class Record.
 * @package WP_MainWP_Stream
 */
class Record {

    /** @var mixed|null Record ID. */
    public $ID;

    /** @var mixed|null Record Timestamp. */
    public $created;

    /** @var mixed|null Child Site ID. */
    public $site_id;

    /** @var mixed|null Blog ID. */
    public $blog_id;

    /** @var mixed|null Object ID. */
    public $object_id;

    /** @var mixed|null User ID. */
    public $user_id;

    /** @var mixed|null User Role. */
    public $user_role;

    /** @var mixed|null User Meta. */
    public $user_meta;

    /** @var mixed|null Record summary. */
    public $summary;

    /** @var mixed|null Connector to use. */
    public $connector;

    /** @var mixed|null Context of action. */
    public $context;

    /** @var mixed|null Action to perform. */
    public $action;

    /** @var mixed|null User IP address. */
    public $ip;

    /** @var mixed|null Meta Data. */
    public $meta;

    /**
     * Record constructor.
     *
     * @param array $item Record.
     */
    public function __construct( $item ) {
		$this->ID        = isset( $item->ID ) ? $item->ID : null;
		$this->created   = isset( $item->created ) ? $item->created : null;
		$this->site_id   = isset( $item->site_id ) ? $item->site_id : null;
		$this->blog_id   = isset( $item->blog_id ) ? $item->blog_id : null;
		$this->object_id = isset( $item->object_id ) ? $item->object_id : null;
		$this->user_id   = isset( $item->user_id ) ? $item->user_id : null;
		$this->user_role = isset( $item->user_role ) ? $item->user_role : null;
		$this->user_meta = isset( $item->meta['user_meta'] ) ? $item->meta['user_meta'] : null;
		$this->summary   = isset( $item->summary ) ? $item->summary : null;
		$this->connector = isset( $item->connector ) ? $item->connector : null;
		$this->context   = isset( $item->context ) ? $item->context : null;
		$this->action    = isset( $item->action ) ? $item->action : null;
		$this->ip        = isset( $item->ip ) ? $item->ip : null;
		$this->meta      = isset( $item->meta ) ? $item->meta : null;

		if ( isset( $this->meta['user_meta'] ) ) {
			unset( $this->meta['user_meta'] );
		}
	}

    /**
     * Save Record.
     *
     * @return false|int|\WP_Error Return FALSE on failure along with error message or return ID of record updated.
     */
    public function save() {
		if ( ! $this->validate() ) {
			return new \WP_Error( 'validation-error', esc_html__( 'Could not validate record data.', 'mainwp-child-reports' ) );
		}

		return wp_mainwp_stream_get_instance()->db->insert( (array) $this );
	}

    /**
     * Populate array.
     *
     * @param array $raw Raw data.
     */
    public function populate(array $raw ) {
		$keys = get_class_vars( $this );
		$data = array_intersect_key( $raw, $keys );
		foreach ( $data as $key => $val ) {
			$this->{$key} = $val;
		}
	}

    /**
     * Validation method.
     *
     * @return bool Default: true.
     */
    public function validate() {
		return true;
	}

	/**
	 * Query record meta.
	 *
	 * @param string $meta_key Meta Key (optional).
	 * @param bool $single Whether or not this is a single record (optional). Default: false.
	 *
	 * @return array Return record query.
	 */
	public function get_meta( $meta_key = '', $single = false ) {
		return get_metadata( 'record', $this->ID, $meta_key, $single );
	}

	/**
	 * Update record meta.
	 *
	 * @param string $meta_key Meta Key
	 * @param mixed $meta_value Meta value.
	 * @param mixed $prev_value Prev Meta value (optional).
	 *
	 * @return bool Return TRUE if record has been updated successfully or FALSE on failure.
	 */
	public function update_meta( $meta_key, $meta_value, $prev_value = '' ) {
		return update_metadata( 'record', $this->ID, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Determine the title of an object that a record is for.
     *
	 * @return string|bool The title of the object as a string, otherwise false.
	 */
	public function get_object_title() {
		if ( ! isset( $this->object_id ) || empty( $this->object_id ) ) {
			return false;
		}

		$output = false;

		if ( isset( $this->meta->post_title ) && ! empty( $this->meta->post_title ) ) {
			$output = (string) $this->meta->post_title;
		} elseif ( isset( $this->meta->display_name ) && ! empty( $this->meta->display_name ) ) {
			$output = (string) $this->meta->display_name;
		} elseif ( isset( $this->meta->name ) && ! empty( $this->meta->name ) ) {
			$output = (string) $this->meta->name;
		}

		return $output;
	}
}
