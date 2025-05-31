<?php
/*
Plugin Name: Plugin Notes
Plugin URI: http://wordpress.org/plugins/plugin-notes/
Description: Allows you to add notes to plugins. Simple and sweet.
Author: Mohammad Jangda
Version: 1.7
Author URI: http://digitalize.ca/
Contributor: Chris Dillon
Contributor URI: http://gapcraft.com/
Contributor: Juliette Reinders Folmer
Contributor URI: http://adviesenzo.nl/
Contributor: moonbuggy
Contributor URI: https://github.com/moonbuggy/wordpress-plugin-notes
Text Domain: plugin-notes
Domain Path: /languages


Copyright 2009-2010 Mohammad Jangda

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

// Avoid direct calls to this file
if ( !function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

/** * @SuppressWarnings(PHPMD.Superglobals) */
if(!class_exists('InputHandler')) {
  class InputHandler {

    public static function postData(string $key): ?string {
      return isset($_POST[$key]) ? $_POST[$key] : Null;
    }

    public static function globalData(string $key): ?string {
      return isset($GLOBALS[$key]) ? $GLOBALS[$key] : Null;
    }

    public static function setGlobalData(string $key, mixed $data): void {
      $GLOBALS[$key] = $data;
    }
  }
}

use \InputHandler as InputHandler;
use \WP_Ajax_Response as WP_Ajax_Response;

/**
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @ SuppressWarnings(PHPMD.Superglobals)
 */
if(!class_exists('plugin_notes')) {
	class plugin_notes {

    const VERSION = 1.7;

    public array $notes = array();
    public string $notesOption = 'plugin_notes';
    private bool $nonceAdded = false;

    private array $allowedTags = array(
			'a' => array(
				'href' => array(),
				'title' => array(),
				'target' => array(),
			),
			'br' => array(),
			'p' => array(),
			'b' => array(),
			'strong' => array(),
			'i' => array(),
			'em' => array(),
			'u' => array(),
			'img' => array(
				'src' => array(),
				'height' => array(),
				'width' => array(),
			),
			'hr' => array(),
		);

    private array $boxcolors = array(
			'#EBF9E6', // light green
			'#F0F8E2', // lighter green
			'#F9F7E6', // light yellow
			'#EAF2FA', // light blue
			'#E6F9F9', // brighter blue
			'#F9E8E6', // light red
			'#F9E6F4', // light pink
			'#F9F0E6', // earth
			'#E9E2F8', // light purple
			'#D7DADD', // light grey
		);
    private string $defaultcolor	= '#EAF2FA';

    private object $inputHandler;

		/**
		 * Object constructor for plugin
		 *
		 * Runs on the admin_init hook
		 */
		function __construct() {
      $this->inputHandler = new InputHandler;

			$this->loadTextdomain();

			$this->notes = $this->getNotes();

			// Add notes to plugin row
			add_filter('plugin_row_meta', array($this, 'pluginRowMeta'), 10, 4);

			// Add string replacement and markdown syntax filters to the note
			add_filter('plugin_notes_note', array($this, 'filterKSES'), 10, 1);
			add_filter('plugin_notes_note', array($this, 'filterVariablesReplace'), 10, 3);

			// the markdown filter doesn'tw ork and if we apply it it empies the stirng
			// if( apply_filters( 'plugin_notes_markdown', true ) ) {
			// 	add_filter('plugin_notes_note', array($this, 'filterMarkdown'), 10, 1);
			// }

			add_filter('plugin_notes_note', array($this, 'filterBreaks'), 10, 1);

			// Add js and css files
			add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));

			// Add helptab
			add_action('admin_head-plugins.php', array($this, 'addHelpTab'));

			// Add ajax action to edit posts
			add_action('wp_ajax_plugin_notes_edit_comment', array($this, 'ajaxEditPluginNote'));

			// Allow filtering of the allowed html tags
			$this->allowedTags = apply_filters('plugin_notes_allowed_tags', $this->allowedTags);
		}

		/**
		 * Localization, what?!
		 */
		private function loadTextdomain(): void {
			load_plugin_textdomain( 'plugin-notes', false, plugin_dir_path(__FILE__) . 'languages/' );
		}


		/**
		 * Adds necessary javascript and css files
		 */
		public function enqueueScripts(): void {

      if($this->inputHandler->globalData('pagenow') === 'plugins.php') {
				$suffix = ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min' );

				wp_enqueue_script('plugin-notes', plugins_url('plugin-notes'.$suffix.'.js', __FILE__), array('jquery', 'wp-ajax-response'), self::VERSION, true);
				wp_enqueue_style('plugin-notes', plugins_url('plugin-notes'.$suffix.'.css', __FILE__), false, self::VERSION, 'all');
				wp_localize_script( 'plugin-notes', 'i18n_plugin_notes', $this->localizeScript() );
			}
		}


		/**
		 * Localize text strings for use in javascript
		 */
		public function localizeScript(): array {
			return array(
				'confirm_delete' => esc_js(__('Are you sure you want to delete this note?', 'plugin-notes')),
				'confirm_new_template' => esc_js(__('Are you sure you want to save this note as a template?\n\rAny changes you made will not be saved to this particular plugin note.\n\r\n\rAlso beware: saving this note as the plugin notes template will overwrite any previously saved templates!', 'plugin-notes')),
				'success_save_template' => esc_js(__('New notes template saved succesfully', 'plugin-notes' )),
			);
		}

		/**
		 * Adds contextual help tab to the plugin page
		 */
		public function addHelpTab(): void {

			$screen = get_current_screen();

			if(method_exists($screen, 'add_help_tab') === true) {
				$screen->add_help_tab( array(
					'id'      => 'plugin-notes-help', // This should be unique for the screen.
					'title'   => 'Plugin Notes',
					'content' => '
						<p>' . sprintf( __( 'The <em><a href="%s">Plugin Notes</a></em> plugin let\'s you add notes for each installed plugin. This can be useful for documenting changes you made or how and where you use a plugin in your website.', 'plugin-notes' ), 'http://wordpress.org/plugins/plugin-notes/" target="_blank" class="ext-link') . '</p>
						<p>' . sprintf( __( 'You can use <a href="%s">Markdown syntax</a> in your notes as well as HTML.', 'plugin-notes' ), 'http://daringfireball.net/projects/markdown/syntax" target="_blank" class="ext-link' ) . '</p>
						<p>' . sprintf( __( 'On top of that, you can even use a <a href="%s">number of variables</a> which will automagically be replaced, such as for example <em>%%WPURI_LINK%%</em> which would be replaced by a link to the WordPress plugin repository for this plugin. Neat isn\'t it ?', 'plugin-notes' ), 'http://wordpress.org/plugins/plugin-notes/faq/" target="_blank" class="ext-link' ) . '</p>
						<p>' . sprintf( __( 'Lastly, you can save a note as a template for new notes. If you use a fixed format for your plugin notes, you will probably like the efficiency of this.', 'plugin-notes' ), '' ) . '</p>
						<p>' . sprintf( __( 'For more information: <a href="%1$s">Plugin home</a> | <a href="%2$s">FAQ</a>', 'plugin-notes' ), 'http://wordpress.org/plugins/plugin-notes/" target="_blank" class="ext-link', 'http://wordpress.org/extend/plugins/plugin-notes/faq/" target="_blank" class="ext-link' ) . '</p>',
					// Use 'callback' instead of 'content' for a function callback that renders the tab content.
					)
				);
			}
		}

		/**
		 * Adds a nonce to the plugin page so we don't get nasty people doing nasty things
		 */
		public function pluginRowMeta(array $pluginMeta, string $pluginFile,
														      array $pluginData): array {

			$note = isset($this->notes[$pluginFile])
								? $this->notes[$pluginFile] : array();
			$this->addPluginNote($note, $pluginData, $pluginFile);

			if(!$this->nonceAdded) {
				?><input type="hidden" name="wp-plugin_notes_nonce" value="<?php
						echo wp_create_nonce('wp-plugin_notes_nonce'); ?>" /><?php
				$this->nonceAdded = true;
			}

			return $pluginMeta;
		}

		/**
		 * Outputs pluging note for the specified plugin
		 *
		 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
		 */
		private function addPluginNote ( ?array $note = null, array $pluginData = [],
																$pluginFile = NULL, $echo = true ) {
			if(empty($pluginData) || empty($pluginFile))
				return;

			$pluginSafeName = $this->getPluginSafeName($pluginData['Name']);
			$actions = array();

			if(is_array($note) && !empty($note['note'])) {
				$noteClass = 'wp-plugin_note_box';

				$noteText = $note['note'];
				$filteredNoteText =
						apply_filters( 'plugin_notes_note', $noteText, $pluginData, $pluginFile );

				$noteAuthor = get_userdata($note['user']);
				$noteDate = $note['date'];

				$noteColor = ( ( isset( $note['color'] ) && $note['color'] !== '' ) ? $note['color'] : $this->defaultcolor );

				$actions[] = '<a href="#" onclick="edit_plugin_note(\''. esc_js( $pluginSafeName ) .'\'); return false;" id="wp-plugin_note_edit'. esc_attr( $pluginSafeName ) .'" class="edit">'. __('Edit note', 'plugin-notes') .'</a>';
				$actions[] = '<a href="#" onclick="delete_plugin_note(\''. esc_js( $pluginSafeName ) .'\'); return false;" id="wp-plugin_note_delete'. esc_attr( $pluginSafeName ) .'" class="delete">'. __('Delete note', 'plugin-notes') .'</a>';
			} else {
				$noteClass = 'wp-plugin_note_box_blank';
				$actions[] = '<a href="#" onclick="edit_plugin_note(\''. esc_js( $pluginSafeName ) .'\'); return false;">'. __('Add plugin note', 'plugin-notes') .'</a>';
				$filteredNoteText = $noteText = '';
				$noteAuthor = null;
				$noteDate = '';
				$noteColor = $this->defaultcolor;
			}

			$noteColorStyle = ( ( $noteColor !== $this->defaultcolor ) ? ' style="background-color: ' . $noteColor . ';"' : '' );

			$output = '
			<div id="wp-plugin_note_' . esc_attr( $pluginSafeName ) . '" ondblclick="edit_plugin_note(\'' . esc_js( $pluginSafeName ) . '\');" title="' . __('Double click to edit me!', 'plugin-notes') . '">
				<span class="wp-plugin_note">' . $filteredNoteText . '</span>
				<span class="wp-plugin_note_user">' . ( ( $noteAuthor ) ? esc_html( $noteAuthor->display_name ) : '' ) . '</span>
				<span class="wp-plugin_note_date">' . esc_html( $noteDate ) . '</span>
				<span class="wp-plugin_note_actions">
					' . implode(' | ', $actions) . '
					<span class="waiting" style="display: none;"><img alt="' . __('Loading...', 'plugin-notes') . '" src="images/wpspin_light.gif" /></span>
				</span>
			</div>';

			$output = apply_filters( 'plugin_notes_row', $output, $pluginData, $pluginFile );

			// Add the form to the note
			$output = '
			<div class="' . $noteClass . '"' . $noteColorStyle . '>
				' . $this->addPluginForm($noteText, $noteColor, $pluginSafeName, $pluginFile, true, false) .
				$output . '
			</div>';

			if($echo !== True)
        return $output;
			echo $output;
		}

		/**
		 * Outputs form to add/edit/delete a plugin note
		 *
		 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
		 */
		private function addPluginForm (string $note = '', ?string $noteColor = NULL,
																?string $pluginSafeName = NULL,
																?string $pluginFile = NULL, bool $hidden = TRUE,
																bool $echo = TRUE): mixed {
			if(empty($pluginSafeName) || empty($pluginFile))
				return Null;

			if(empty($noteColor))
				$noteColor = $this->defaultcolor;
			$pluginFormStyle = ($hidden) ? 'style="display:none"' : '';

			$newNoteClass = '';
			if( $note === '' ) {
				$note = ( isset( $this->notes['plugin-notes_template'] ) ? $this->notes['plugin-notes_template'] : '' );
				$newNoteClass = ' class="new_note"';
			}
			$pluginSafeNameEsc = esc_attr( $pluginSafeName );

			$output = '
			<div id="wp-plugin_note_form_' . $pluginSafeNameEsc . '" class="wp-plugin_note_form" ' . $pluginFormStyle . '>
				 <label for="wp-plugin_note_color_' . $pluginSafeNameEsc . '">' . __( 'Note color:', 'plugin-notes') . '
				 <select name="wp-plugin_note_color_' . $pluginSafeNameEsc . '" id="wp-plugin_note_color_' . esc_attr( $pluginSafeName ) . '">
			';

			// Add color options
			foreach( $this->boxcolors as $color ){
				$output .= '
					<option value="' . $color . '" style="background-color: ' . $color . '; color: ' . $color . ';"' .
					( ( $color === $noteColor ) ? ' selected="selected"' : '' ) .
					'>' . $color . '</option>';
			}

			$output .= '
				</select></label>
				<!-- <textarea name="wp-plugin_note_text_' . esc_attr( $pluginSafeName ) . '" cols="90" rows="10"' . $newNoteClass . '>' . esc_textarea( $note ) . '</textarea> -->
				<textarea name="wp-plugin_noteText_' . esc_attr( $pluginSafeName ) . '" cols="90" rows="10"' . $newNoteClass . '>' . esc_textarea( $note ) . '</textarea>
				<span class="wp-plugin_note_error error" style="display: none;"></span>
				<span class="wp-plugin_note_success success" style="display: none;"></span>
				<span class="wp-plugin_note_edit_actions">
'.					// TODO: Unobtrusify the javascript
'					<a href="#" onclick="save_plugin_note(\'' . esc_js( $pluginSafeName ) . '\');return false;" class="button-primary">' . __('Save', 'plugin-notes') . '</a>
					<a href="#" onclick="cancel_plugin_note(\'' . esc_js( $pluginSafeName ) . '\');return false;" class="button">' . __('Cancel', 'plugin-notes') . '</a>
					<a href="#" onclick="templatesave_plugin_note(\'' . esc_js( $pluginSafeName ) . '\');return false;" class="button-secondary">' . __('Save as template for new notes', 'plugin-notes') . '</a>
					<span class="waiting" style="display: none;"><img alt="' . __('Loading...', 'plugin-notes') . '" src="images/wpspin_light.gif" /></span>
				</span>
				<input type="hidden" name="wp-plugin_note_slug_' . esc_attr( $pluginSafeName ) . '" value="' . esc_attr( $pluginFile ) . '" />
				<input type="hidden" name="wp-plugin_note_new_template_' . esc_attr( $pluginSafeName ) . '" id="wp-plugin_note_new_template_' . esc_attr( $pluginSafeName ) . '" value="n" />
			</div>';

			if( $echo !== true )
				return apply_filters( 'plugin_notes_form', $output, $pluginSafeName );
			echo apply_filters( 'plugin_notes_form', $output, $pluginSafeName );
		}


		/**
		 * Returns a cleaned up version of the plugin name, i.e. it's slug
		 */
		private function getPluginSafeName(string $name): string {
			return sanitize_title($name);
		}


		/**
		 * Function that handles editing of the plugin via AJAX
		 *
		 * @SuppressWarnings(PHPMD.ExitExpression)
		 */
		public function ajaxEditPluginNote(): void {
			global $currentUser;

			// Verify nonce
      if(!wp_verify_nonce($this->inputHandler->postData('_nonce'), 'wp-plugin_notes_nonce')) {
				die( __( 'Don\'t think you\'re supposed to be here...', 'plugin-notes' ) );
				return;
			}

			$currentUser = wp_get_current_user();

			if(!current_user_can('activate_plugins')) {
				// user can't edit plugins, so throw error
				die( __( 'Sorry, you do not have permission to edit plugins.', 'plugin-notes' ) );
				return;
			}

			// Get notes array
			$notes = $this->getNotes();
      $noteText = $this->filterKSES(stripslashes(trim($this->inputHandler->postData('plugin_note'))));
      $noteColor = ($this->inputHandler->postData('plugin_note_color') !== Null
                    && in_array($this->inputHandler->postData('plugin_note_color'), $this->boxcolors )
                      ? $this->inputHandler->postData('plugin_note_color')
                      : $this->defaultcolor);
			// TODO: Escape this?
      $plugin = $this->inputHandler->postData('plugin_slug');
      $pluginName = esc_html($this->inputHandler->postData('plugin_name'));

			$responseData = array();
			$responseData['slug'] = $plugin;

			$note = array();

			if($noteText) {

				// Are we trying to save the note as a note template ?
        if($this->inputHandler->postData('plugin_new_template') === 'y' ) {

					$notes['plugin-notes_template'] = $noteText;

					$responseData = array_merge($responseData, $note);
					$responseData['action'] = 'save_template';
				}

				// Ok, no template, save the note to the specific plugin
				else {
					$dateFormat = get_option('date_format');

					// setup the note data
					$note['date'] = date($dateFormat);
					$note['user'] = $currentUser->ID;
					$note['note'] = $noteText;
					$note['color'] = $noteColor;

					// Add new note to notes array
					$notes[$plugin] = $note;

					$responseData = array_merge($responseData, $note);
					$responseData['action'] = 'edit';
				}

			} else {
				// no note sent, so let's delete it
				if(!empty($notes[$plugin])) unset($notes[$plugin]);
				$responseData['action'] = 'delete';
			}

			// Save the new notes array
			$this->setNotes($notes);

			// Prepare response
			$response = new WP_Ajax_Response();

			$pluginNoteContent = $this->addPluginNote($note,
                              array('Name' => $pluginName), $plugin, false);
			$response->add(array(
				'what' => 'plugin_note',
				'id' => $plugin,
				'data' => $pluginNoteContent,
				'action' => (($noteText)
            ? (($this->inputHandler->postData('plugin_new_template') === 'y' )
              ? 'save_template' : 'edit' ) : 'delete' ),
			));
			$response->send();

			return;
		}


		/**
		 * Applies the wp_kses html filter to the note string
		 *
		 * @param		string	$pluginNote
		 * @return		string	altered string $pluginNote
		 */
		public function filterKSES( string $pluginNote ): string {
			return wp_kses( $pluginNote, $this->allowedTags );
		}


		/**
		 * Adds additional line breaks to the note string
		 *
		 * @param		string	$pluginNote
		 * @return		string	altered string $pluginNote
		 */
		public function filterBreaks(string $pluginNote): string {
			return wpautop( $pluginNote );
		}


		/**
		 * Applies markdown syntax filter to the note string
		 *
		 * @param		string	$pluginNote
		 * @return		string	altered string $pluginNote
		 */
		public function filterMarkdown(string $pluginNote): string {
			include_once( dirname(__FILE__) . '/inc/markdown/markdown.php' );

			return Markdown( $pluginNote );
		}


		/**
		 * Replaces a number of variables in the note string
		 *
		 * @param		string	$pluginNote
		 * @return		string	altered string $pluginNote
		 */
		public function filterVariablesReplace(string $pluginNote, array $pluginData,
																			string $pluginFile): string {

			if( !isset($pluginData ) || count( $pluginData ) === 1 ) {
				$pluginData = get_plugin_data( WP_PLUGIN_DIR . '/' . $pluginFile, false, $translate = true );
			}

			$find = array(
				'%NAME%',
				'%PLUGIN_PATH%',
				'%URI%',
				'%WPURI%',
				'%WPURI_LINK%',
				'%AUTHOR%',
				'%AUTHORURI%',
				'%VERSION%',
				'%DESCRIPTION%',
			);
			$replace = array(
				esc_html($pluginData['Name']),
				esc_html( plugins_url() . '/' . plugin_dir_path( $pluginFile ) ),
				( isset( $pluginData['PluginURI'] ) ? esc_url( $pluginData['PluginURI'] ) : '' ),
				esc_url( 'http://wordpress.org/plugins/' . substr( $pluginFile, 0, strpos( $pluginFile, '/') ) ),
				'<a href="' . esc_url( 'http://wordpress.org/plugins/' . substr( $pluginFile, 0, strpos( $pluginFile, '/') ) ) . '" target="_blank">' . esc_html($pluginData['Name']) . '</a>',
				( isset($pluginData['Author'] ) ? esc_html( $pluginData['Author'] ) : '' ),
				( isset($pluginData['AuthorURI'] ) ? esc_html( $pluginData['AuthorURI'] ) : '' ),
				( isset($pluginData['Version'] ) ? esc_html( $pluginData['Version'] ) : '' ),
				( isset($pluginData['Description'] ) ? esc_html( $pluginData['Description'] ) : '' ),
			);

			return str_replace( $find, $replace, $pluginNote );
		}


		/* Some sweet function to get/set go!*/
		private function getNotes(): mixed {
			return get_option($this->notesOption);
		}

		private function setNotes(mixed $notes): bool {
			return update_option($this->notesOption, $notes);
		}

	} /* End of class */


	add_action( 'admin_init', 'pluginNotesInit' );

  /** * @SuppressWarnings(PHPMD.Superglobals) */
  function pluginNotesInit(): void {
		/** Let's get the plugin rolling **/
		// Create new instance of the plugin_notes object
    $GLOBALS['plugin_notes'] = new plugin_notes();
	}

} /* End of class-exists wrapper */
