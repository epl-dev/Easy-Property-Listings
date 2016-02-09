<?php
/**
 * Contact Object
 *
 * @package     EPL
 * @since       2.4
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EPL_Contact Class
 *
 * @since 2.4
 */
class EPL_Contact {

	/**
	 * The contact ID
	 *
	 * @since 2.4
	 */
	public $ID = 0;

	/**
	 * The contact's listing count
	 *
	 * @since 2.4
	 */
	public $listing_count = 0;


	/**
	 * The contact's email
	 *
	 * @since 2.4
	 */
	public $email;

	/**
	 * The contact's name
	 *
	 * @since 2.4
	 */
	public $name;

	/**
	 * The contact's creation date
	 *
	 * @since 2.4
	 */
	public $date_created;

	/**
	 * The listing IDs associated with the contact
	 *
	 * @since  2.4
	 */
	public $listing_ids;

	/**
	 * Contact Notes
	 *
	 * @since  2.4
	 */
	public $notes;

	/**
	 * Get things going
	 *
	 * @since 2.4
	 */
	public function __construct( $_id_or_email = false) {

		if ( false === $_id_or_email || ( is_numeric( $_id_or_email ) && (int) $_id_or_email !== absint( $_id_or_email ) ) ) {
			return false;
		}

		if ( is_numeric( $_id_or_email ) ) {
			$field =  'id';
		} else {
			$field = 'email';
		}

		$contact = $this->get_contact_by( $field, $_id_or_email );

		if ( empty( $contact ) || ! is_object( $contact ) ) {
			return false;
		}
		$this->setup_contact( $contact );

	}
	
	/**
	 * Get the email of contact
	 */
	function get_contact_email($id) {
		return get_post_meta($id,'contact_email',true);
	}
	
	/**
	 * update the email of contact
	 */
	function update_contact_email($id,$value) {
		return update_post_meta($id,'contact_email',$value);
	}

	/**
	 * Given the contact data, let's set the variables
	 *
	 * @since  2.4
	 * @param  object $contact The Contact Object
	 * @return bool             If the setup was successful or not
	 */
	private function setup_contact( $contact ) {

		if ( ! is_object( $contact ) ) {
			return false;
		}

		foreach ( array('ID','name','email','notes','listing_ids','listing_count','date_created') as $key ) {

			switch ( $key ) {
			
				case 'ID':
					$this->$key = $contact->ID;
					$this->id = $contact->ID;
					break;
					
				case 'email':
					$this->$key = $this->get_contact_email($contact->ID);
					break;
					

				case 'notes':
					$this->$key = $this->get_notes();
					break;
					
				case 'listing_ids':
					$this->$key = $this->get_listings();
					break;
					
				case 'listing_count':
					$this->$key = $this->get_listing_count();
					break;
					
				case 'date_created':
					$this->$key = $contact->post_date;
					break;
					
				case 'name':
					$this->$key = $contact->post_title;
					break;


			}

		}

		// Contact ID and email are the only things that are necessary, make sure they exist
		if ( ! empty( $this->ID ) && ! empty( $this->email ) ) {
			return true;
		}

		return false;

	}
	
	/**
	 * Retrieves a single contact from the database
	 *
	 * @access public
	 * @since  2.4
	 * @param  string $column id or email
	 * @param  mixed  $value  The Contact ID or email to search
	 * @return mixed          Upon success, an object of the contact. Upon failure, NULL
	 */
	public function get_contact_by( $field = 'id', $value = 0 ) {
		global $wpdb;

		if ( empty( $field ) || empty( $value ) ) {
			return NULL;
		}

		if ( 'id' == $field ) {
			// Make sure the value is numeric to avoid casting objects, for example,
			// to int 1.
			if ( ! is_numeric( $value ) ) {
				return false;
			}

			$value = intval( $value );

			if ( $value < 1 ) {
				return false;
			}

		} elseif ( 'email' === $field ) {

			if ( ! is_email( $value ) ) {
				return false;
			}

			$value = trim( $value );
		}

		if ( ! $value ) {
			return false;
		}

		switch ( $field ) {
			case 'id':
				$contact  = get_post($value);
				break;
			case 'email':
				$matched_contacts = new WP_Query(
					array(
						'post_type'		=>	'epl_contact',
						'post_status'	=>	'publish',
						'meta_query'	=>	array(
							array(
								'key'	=>	'contact_email',
								'value'	=>	$value
							)
						)
					)
				);
				$contact  = $matched_contacts->post;
				break;
			default:
				return false;
		}

		if ( ! $contact  ) {
			return false;
		}

		return $contact;
	}
	

	/**
	 * Magic __get function to dispatch a call to retrieve a private property
	 *
	 * @since 2.4
	 */
	public function __get( $key ) {
		
		if( method_exists( $this, 'get_' . $key ) ) {

			return call_user_func( array( $this, 'get_' . $key ) );

		} else {

			return new WP_Error( 'epl-contact-invalid-property', sprintf( __( 'Can\'t get property %s', 'epl' ), $key ) );

		}

	}

	/**
	 * Creates a contact
	 *
	 * @since  2.4
	 * @param  array  $data Array of attributes for a contact
	 * @return mixed        False if not a valid creation, Contact ID if user is found or valid creation
	 */
	public function create( $data = array() ) {

		if ( $this->ID != 0 || empty( $data ) ) {
			return false;
		}

		$defaults = array(
			'name' 	=> '',
			'email'	=>	''
		);

		$args = wp_parse_args( $data, $defaults );


		if ( empty( $args['email'] ) || ! is_email( $args['email'] ) ) {
			return false;
		}

		do_action( 'epl_contact_pre_create', $args );

		$created = false;

		if( $contact = $this->contact_exists($args['email']) ) {
			wp_update_post(
				array(
					'post_title'	=>	$args['name'],
					'post_type'		=>	'epl_contact',
					'ID'			=>	$contact->ID
				)
			);
			
		} else {
			
			$inserted = wp_insert_post( 
				array(
					'post_title'		=>	$args['name'],
					'post_type'			=>	'epl_contact',
					'post_status'		=>	'publish',
				) 
			);
		
			if($inserted) {
				$this->update_contact_email($inserted,$args['email']);
				$contact = $this->get_contact_by( 'email', $args['email'] );
				$this->setup_contact( $contact );
			}

		
		}
		$created = $contact->ID;

		do_action( 'epl_contact_post_create', $created, $args );
		return $created;

	}
	
	function contact_exists($email=null) {
		$matched_contacts = new WP_Query(
			array(
				'post_type'		=>	'epl_contact',
				'post_status'	=>	'publish',
				'meta_query'	=>	array(
					array(
						'key'	=>	'contact_email',
						'value'	=>	$email
					)
				)
			)
		);
		return !empty($matched_contacts->post) ? $matched_contacts->post : false;
	}

	/**
	 * Update a contact record
	 *
	 * @since  2.4
	 * @param  array  $data Array of data attributes for a contact (checked via whitelist)
	 * @return bool         If the update was successful or not
	 */
	public function update( $data = array() ) {

		if ( empty( $data ) ) {
			return false;
		}

		do_action( 'epl_contact_pre_update', $this->ID, $data );

		$updated = false;
		
		$updated = wp_update_post(
			array(
				'post_title'	=>	$data['name'],
				'post_type'		=>	'epl_contact',
				'ID'			=>	$this->ID
			)
		);
		if ( $updated ) {
		
			if( isset($data['email']) && is_email($data['email']) ) { 
				// make sure email is new & not already attached to some other contact
				if( ! $this->contact_exists($data['email']) && ($data['email'] != $this->email) ) {
					$this->update_contact_email($this->ID,$data['email']);
				}
			}

			$contact = $this->get_contact_by( 'id', $this->ID );
			$this->setup_contact( $contact);

			$updated = true;
		}

		do_action( 'epl_contact_post_update', $updated, $this->ID, $data );

		return $updated;
	}


	/**
	 * Attach listing to the contact then triggers increasing stats
	 *
	 * @since  2.4
	 * @param  int $listing_id The listing ID to attach to the contact
	 * @return bool            If the attachment was successfuly
	 */
	public function attach_listing( $listing_id = 0) {

		if( empty( $listing_id ) ) {
			return false;
		}

		if( empty( $this->listing_ids ) ) {

			$new_listing_ids = array($listing_id);

		} else {

			if ( in_array( $listing_id, $this->listing_ids ) ) {
			}

			 $this->listing_ids[] = $listing_id;

			$new_listing_ids = array_unique( array_values(  $this->listing_ids ) );

		}

		do_action( 'epl_contact_pre_attach_listing', $listing_id, $this->ID );
			
		$listing_added = update_post_meta($this->ID,'contact_interested_listings',$new_listing_ids);

		if ( $listing_added ) {

		
			// update the contacts of listing as well
			$contact_ids = get_post_meta($listing_id,'epl_interested_contacts',true);
			if(!empty($contact_ids)) {
				$contact_ids[] = $this->ID;
			} else {
				$contact_ids = array($this->ID);
			}
			update_post_meta($listing_id,'epl_interested_contacts',$contact_ids);

		}

		do_action( 'epl_contact_post_attach_listing', $listing_added, $listing_id, $this->ID );

		return $listing_added;
	}


	/**
	 * Remove a listing from this contact, then triggers reducing stats
	 *
	 * @since  2.4
	 * @param  integer $listing_id The Payment ID to remove
	 * @return boolean             If the removal was successful
	 */
	public function remove_listing( $listing_id = 0 ) {

		if( empty( $listing_id ) ) {
			return false;
		}

		$new_listing_ids = '';

		if( ! empty( $this->listing_ids ) ) {

			$pos = array_search( $listing_id, $this->listing_ids );
			if ( false === $pos ) {
				return false;
			}

			unset( $this->listing_ids[ $pos ] );
			$listing_ids = array_filter( $this->listing_ids );

			$new_listing_ids = array_unique( array_values( $listing_ids ) );

		}

		do_action( 'epl_contact_pre_remove_listing', $listing_id, $this->ID );

		$listing_removed = $listing_added = update_post_meta($this->ID,'contact_interested_listings',$new_listing_ids);

		if ( $listing_removed ) {



		}
		// update the contacts of listing as well
		$contact_ids = get_post_meta($listing_id,'epl_interested_contacts',true);
		if(!empty($contact_ids)) {
			$pos = array_search( $this->ID, $contact_ids );
			if ( false !== $pos ) {
				unset( $contact_ids[ $pos ] );
				update_post_meta($listing_id,'epl_interested_contacts',$contact_ids);		
			}

			
		}

		do_action( 'epl_contact_post_remove_listing', $listing_removed, $listing_id, $this->ID );

		return $listing_removed;

	}
	
	
	/**
	 * get listings of contact
	 *
	 * @since  2.4
	 */
	public function get_listings( ) {
		
		return get_post_meta($this->ID,'contact_interested_listings',true);
		
	}
	
	/**
	 * get listings count
	 *
	 * @since  2.4
	 */
	public function get_listing_count( ) {
		
		$listings =  get_post_meta($this->ID,'contact_interested_listings',true);
		return count($listings);
		
	}
	

	/**
	 * Get the parsed notes for a contact as an array
	 *
	 * @since  2.4
	 * @param  integer $length The number of notes to get
	 * @param  integer $paged What note to start at
	 * @return array           The notes requsted
	 */
	public function get_notes( $number = 20, $paged = 1 ) {

		$length = is_numeric( $length ) ? $length : 20;
		$offset = is_numeric( $paged ) && $paged != 1 ? ( ( absint( $paged ) - 1 ) * $number ) : 0;

		return get_comments( array( 'meta_key' => 'epl_contact_id', 'meta_value' => $this->ID, 'offset'	=>	$offset, 'number'	=>	$number ) );

	}

	/**
	 * Get the total number of notes we have after parsing
	 *
	 * @since  2.4
	 * @return int The number of notes for the contact
	 */
	public function get_notes_count() {
		
		return get_comments( array( 'meta_key' => 'epl_contact_id', 'meta_value' => $this->ID , 'count' => true) );

	}

	/**
	 * Add a note for the contact
	 *
	 * @since  2.4
	 * @param string $note The note to add
	 * @return string|boolean The new note if added succesfully, false otherwise
	 */
	public function add_note( $note = '',$note_type='epl_user_note',$listing_id=0 ) {

		$note = trim( $note );
		if ( empty( $note ) ) {
			return false;
		}
		
		$note    = apply_filters( 'epl_contact_add_note_string', $note, $note_type, $listing_id );

		do_action( 'epl_contact_pre_add_note', $note, $this->ID );
		
		$notedata = array(
			'comment_post_ID' 		=> absint($listing_id),
			'comment_author' 		=> $this->name,
			'comment_author_email' 	=> $this->email,
			'comment_content' 		=> $note, 
			'comment_type' 			=> $note_type,
			'comment_parent' 		=> 0,
			'comment_agent' 		=> 'epl',
		);

		//Insert new note and get the note ID
		if ( $note_id = wp_insert_comment( $notedata ) ) {
			$this->notes = $this->get_notes();
			update_comment_meta($note_id,'epl_contact_id',$this->ID);
		}

		do_action( 'epl_contact_post_add_note', $this->notes, $new_note, $this->ID, $note_type, $listing_id );
		
		// Return the note object
		return get_comment($note_id);

	}
	
	
	/**
	 * delete the contact
	 *
	 * @since  2.4
	 */
	public function delete() {
		return wp_delete_post($this->ID,true);
	}
	
	
	public function update_meta($key,$value) {
		return update_post_meta($this->ID,$key,$value);
	}
	
	public function get_meta($key) {
		return get_post_meta($this->ID,$key,true);
	}
}
