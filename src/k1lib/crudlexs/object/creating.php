<?php

namespace k1lib\crudlexs\object;

use k1lib\html\bootstrap\components\grid_row;
use k1lib\html\bootstrap\components\label_value_row;
use k1lib\html\div;
use k1lib\html\form;
use k1lib\html\input;
use k1lib\html\label;
use k1lib\notifications\on_DOM as DOM_notification;
use function k1lib\common\clean_array_with_guide;
use function k1lib\common\unserialize_var;
use function k1lib\common\unset_serialize_var;
use function k1lib\forms\check_all_incomming_vars;
use function k1lib\html\get_link_button;
use function k1lib\html\html_header_go;

/**
 * Creating object for handling new record creation.
 * Manages form rendering, data validation, and database insertion.
 */
class creating extends base_with_data implements base_interface {

    /**
     * Array of POST data received from form submission.
     * @var array
     */
    protected $post_incoming_array = [];

    /**
     * Flag indicating whether POST data has been captured.
     * @var bool
     */
    protected $post_data_catched = FALSE;

    /**
     * Array of validation errors from POST data processing.
     * @var array
     */
    protected $post_validation_errors = [];

    /**
     * Array of password field names for special handling.
     * @var array
     */
    protected $post_password_fields = [];

    /**
     * Current state of the object (create or update).
     * @var string
     */
    protected $object_state = "create";

    /**
     * Flag to enable Foundation form validation.
     * @var bool
     */
    protected $enable_foundation_form_check = FALSE;

    /**
     * Whether to show the cancel button in the form.
     * @var bool
     */
    protected $show_cancel_button = TRUE;

    /**
     * Result of the insert operation.
     * @var mixed
     */
    protected $inserted_result = NULL;

    /**
     * Flag indicating if record was successfully inserted.
     * @var mixed
     */
    protected $inserted = NULL;

    /**
     * CSS classes for form column layout.
     * @var string
     */
    protected $html_form_column_classes = "lg-8 md-10 sm-11";

    /**
     * CSS classes for data column layout.
     * @var string
     */
    protected $html_column_classes = "sm-12 column";

    /**
     * Constructs a creating object for new record creation.
     *
     * @param mixed $db_table The database table object.
     * @param mixed $row_keys_text Row keys text (typically FALSE for create).
     */
    public function __construct($db_table, $row_keys_text) {
        parent::__construct($db_table, $row_keys_text);
    }

    /**
     * Loads database table data, optionally with blank values for create forms.
     * Overrides parent method to create an empty array structure for all fields.
     *
     * @param bool $blank_data If TRUE, loads with empty values instead of from DB.
     * @return bool TRUE on success, FALSE otherwise.
     */
    public function load_db_table_data($blank_data = FALSE): bool {
        if (!$blank_data) {
            return parent::load_db_table_data();
        } else {
            $headers_array = [];
            $blank_row_array = [];
            $show_rule = $this->db_table->get_db_table_show_rule();
            foreach ($this->db_table->get_db_table_config() as $field => $config) {
                if (!empty($this->db_table->get_constant_fields()) && array_key_exists($field, $this->db_table->get_constant_fields())) {
                    continue;
                }
                if (($show_rule === NULL) || ($config[$show_rule])) {
                    $headers_array[$field] = $field;
                    $blank_row_array[$field] = "";
                }
            }
            if (!empty($headers_array) && !empty($blank_row_array)) {
                if ($this->db_table_data == false) {
                    $this->db_table_data = [];
                }
                $this->db_table_data[0] = $headers_array;
                $this->db_table_data[1] = $blank_row_array;
                $this->db_table_data_filtered = $this->db_table_data;
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }

    /**
     * Sets a specific incoming POST value for a field.
     *
     * @param string $field The field name to set.
     * @param mixed $value The value to assign.
     * @return bool TRUE if value was set, FALSE otherwise.
     */
    public function set_post_incomming_value($field, $value): bool {
        if ($this->post_data_catched && key_exists($field, $this->post_incoming_array)) {
            $this->post_incoming_array[$field] = $value;
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Transfers captured POST data into the table data structure.
     * Must be called before any filters are applied.
     *
     * @return bool TRUE if data was transferred, FALSE otherwise.
     */
    public function put_post_data_on_table_data(): bool {
        if ((empty($this->db_table_data)) || empty($this->post_incoming_array)) {
            return FALSE;
        }
        foreach ($this->db_table_data[1] as $field => $value) {
            if (isset($this->post_incoming_array[$field])) {
                $this->db_table_data[1][$field] = $this->post_incoming_array[$field];
            }
        }
        $this->db_table_data_filtered = $this->db_table_data;
        return TRUE;
    }

    /**
     * Validates password field submissions including current, new, and confirm values.
     * Handles both create and update scenarios with appropriate validation.
     *
     * @return void
     */
    function do_password_fields_validation(): void {
        $password_fields = [];
        $current = null;
        $new = null;
        $confirm = null;

        foreach ($_POST as $field => $value) {
            $actual_password_field = strstr($field, "_password_", TRUE);
            if ($actual_password_field !== FALSE) {
                if (strstr($field, "_password_current") !== FALSE) {
                    $password_fields[$actual_password_field]['current'] = (empty($value)) ? NULL : hash('sha256', $value);
                }
                if (strstr($field, "_password_new") !== FALSE) {
                    $password_fields[$actual_password_field]['new'] = (empty($value)) ? NULL : hash('sha256', $value);
                }
                if (strstr($field, "_password_confirm") !== FALSE) {
                    $password_fields[$actual_password_field]['confirm'] = (empty($value)) ? NULL : hash('sha256', $value);
                }
                unset($_POST[$field]);
                if ($this->do_table_field_name_encrypt) {
                    $this->post_password_fields[] = $this->decrypt_field_name($field);
                } else {
                    $this->post_password_fields[] = $field;
                }
            }
        }

        foreach ($password_fields as $field => $passwords) {
            if (array_key_exists('new', $passwords) && array_key_exists('confirm', $passwords)) {
                if (($passwords['new'] === $passwords['confirm']) && (!empty($passwords['new']))) {
                    $new_password = TRUE;
                } else {
                    $new_password = FALSE;
                }
            }
            if (array_key_exists('current', $passwords) && array_key_exists('new', $passwords) && array_key_exists('confirm', $passwords)) {
                if (empty($passwords['current'])) {
                    $this->post_incoming_array[$field] = $this->db_table_data[1][$this->decrypt_field_name($field)];
                } else {
                    if (($passwords['current'] === $this->db_table_data[1][$this->decrypt_field_name($field)])) {
                        if ($new_password) {
                            $this->post_incoming_array[$field] = $passwords['new'];
                            DOM_notification::queue_mesasage(updating_strings::$password_set_successfully, "success", $this->notifications_div_id);
                        } else {
                            $this->post_validation_errors[$this->decrypt_field_name($field)] = creating_strings::$error_new_password_not_match;
                        }
                    } else {
                        $this->post_validation_errors[$this->decrypt_field_name($field)] = creating_strings::$error_actual_password_not_match;
                    }
                }
            } else if (array_key_exists('new', $passwords) && array_key_exists('confirm', $passwords)) {
                if ($new_password) {
                    $this->post_incoming_array[$field] = $passwords['new'];
                } else {
                    $this->post_incoming_array[$field] = null;
                    if (empty($passwords['new'])) {
                        $this->post_validation_errors[$this->decrypt_field_name($field)] = creating_strings::$error_new_password_not_match;
                    }
                }
            }
        }
    }

    /**
     * Checks if POST data has been captured.
     *
     * @return bool TRUE if data is captured, FALSE otherwise.
     */
    public function get_post_data_catched(): bool {
        return $this->post_data_catched;
    }

    /**
     * Captures and processes POST data from form submission.
     * Validates, decrypts field names if encryption is enabled, and cleans the data.
     *
     * @return bool TRUE if data was captured successfully, FALSE otherwise.
     */
    function catch_post_data(): bool {
        $this->do_file_uploads_validation();
        $this->do_password_fields_validation();

        $post_data_to_use = unserialize_var("post-data-to-use");
        $post_data_table_config = unserialize_var("post-data-table-config");

        $fk_found_array = [];
        $found_fk_key = false;
        if (!empty($post_data_table_config)) {
            foreach ($post_data_table_config as $field => $field_config) {
                if (!empty($field_config['refereced_column_config'])) {
                    $fk_field_name = $field_config['refereced_column_config']['field'];
                    foreach ($post_data_to_use as $field_current => $value) {
                        if (($field_current == $fk_field_name) && ($field != $field_current)) {
                            $fk_found_array[$field] = $value;
                            $found_fk_key = true;
                        }
                    }
                }
            }
        }

        if (!empty($post_data_to_use)) {
            $_POST = $post_data_to_use;
            unset_serialize_var("post-data-to-use");
            unset_serialize_var("post-data-table-config");
        }

        $_POST = check_all_incomming_vars($_POST);

        $this->post_incoming_array = array_merge($this->post_incoming_array, $_POST);
        if (isset($this->post_incoming_array['k1magic'])) {
            self::set_k1magic_value($this->post_incoming_array['k1magic']);
            unset($this->post_incoming_array['k1magic']);

            if (!empty($this->post_incoming_array)) {
                if ($this->do_table_field_name_encrypt) {
                    $new_post_data = [];
                    foreach ($this->post_incoming_array as $field => $value) {
                        $decrypt_field_name = $this->decrypt_field_name($field);
                        if (array_key_exists($decrypt_field_name, $fk_found_array)) {
                            $value = $fk_found_array[$decrypt_field_name];
                        }
                        $new_post_data[$decrypt_field_name] = $value;
                    }
                    $this->post_incoming_array = $new_post_data;
                    unset($new_post_data);
                }
                $this->post_incoming_array = clean_array_with_guide($this->post_incoming_array, $this->db_table->get_db_table_config());

                $this->post_data_catched = TRUE;
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Inserts HTML input elements into the data row based on field types.
     * Creates appropriate input controls for each database field.
     *
     * @param bool $create_labels_tags_on_headers Whether to create label tags for headers.
     * @return void
     */
    public function insert_inputs_on_data_row($create_labels_tags_on_headers = TRUE): void {
        $row_to_apply = 1;

        foreach ($this->db_table_data_filtered[$row_to_apply] as $field => $value) {
            switch ($this->db_table->get_field_config($field, 'type')) {
                case 'enum':
                    $input_tag = input_helper::enum_type($this, $field);
                    break;
                case 'text':
                    switch ($this->db_table->get_field_config($field, 'validation')) {
                        case "html":
                            $input_tag = input_helper::text_type($this, $field);
                            break;
                        default:
                            $input_tag = input_helper::text_type($this, $field, FALSE);
                            break;
                    }
                    break;
                default:
                    switch ($this->db_table->get_field_config($field, 'validation')) {
                        case "boolean":
                            $input_tag = input_helper::boolean_type($this, $field);
                            break;
                        case "file-upload":
                            $input_tag = input_helper::file_upload($this, $field);
                            break;
                        case "password":
                            $input_tag = input_helper::password_type($this, $field, $this->object_state);
                            break;
                        default:
                            $input_tag = input_helper::default_type($this, $field);
                            break;
                    }
                    break;
            }

            if ($create_labels_tags_on_headers) {
                $label_tag = new label($this->db_table_data_filtered[0][$field], $this->encrypt_field_name($field));
                if ($this->db_table->get_field_config($field, 'required') === TRUE) {
                    $label_tag->set_value(" *", TRUE);
                }
                if (isset($this->post_validation_errors[$field])) {
                    $label_tag->set_attrib("class", "is-invalid-label");
                }
                $this->db_table_data_filtered[0][$field] = $label_tag;
            }

            if (isset($this->post_validation_errors[$field])) {
                $div_error = new grid_row(2);

                $div_input = $div_error->cell(1)->large(12);
                $div_message = $div_error->cell(2)->large(12);

                $span_error = $div_message->append_span("clearfix form-error is-visible");
                $span_error->set_value($this->post_validation_errors[$field]);

                $input_tag->append_to($div_input);
                $input_tag->set_attrib("class", "is-invalid-input", TRUE);

                $div_error->link_value_obj($input_tag);
            }

            if ($this->db_table->get_field_config($field, 'required') === TRUE) {
                if ($this->enable_foundation_form_check) {
                    $input_tag->set_attrib("required", TRUE);
                }
            }
            $input_tag->set_attrib("k1lib-data-type", $this->db_table->get_field_config($field, 'validation'));
            $input_tag->set_attrib("id", $this->encrypt_field_name($field));

            if (isset($div_error)) {
                $this->apply_html_tag_on_field_filter($div_error, $field);
                unset($div_error);
            } else {
                $this->apply_html_tag_on_field_filter($input_tag, $field);
            }

            unset($input_tag);
        }
    }

    /**
     * Validates all POST data against the database table configuration.
     *
     * @return bool TRUE if no errors found, FALSE otherwise.
     */
    public function do_post_data_validation(): bool {
        $validation_result = $this->db_table->do_data_validation($this->post_incoming_array);
        if ($validation_result !== TRUE) {
            $this->post_validation_errors = array_merge($this->post_validation_errors, $validation_result);
        }
        if (empty($this->post_validation_errors)) {
            return TRUE;
        } else {
            if ($this->object_state == "create") {
                foreach ($this->post_password_fields as $field) {
                    $this->db_table_data[1][$field] = null;
                    $this->db_table_data_filtered[1][$field] = null;
                }
            }
            return FALSE;
        }
    }

    /**
     * Validates file upload submissions from $_FILES.
     *
     * @return void
     */
    public function do_file_uploads_validation(): void {
        if (!empty($_FILES)) {
            foreach ($_FILES as $encoded_field => $data) {
                $decoded_field = $this->decrypt_field_name($encoded_field);
                if ($data['error'] === UPLOAD_ERR_OK) {
                    $_POST[$decoded_field] = $data;
                } else {
                    if ($data['error'] !== UPLOAD_ERR_NO_FILE) {
                        trigger_error(creating_strings::$error_file_upload . print_r($data, TRUE), E_USER_WARNING);
                    }
                }
            }
        }
    }

    /**
     * Enables Foundation form validation on inputs.
     *
     * @return void
     */
    public function enable_foundation_form_check(): void {
        $this->enable_foundation_form_check = TRUE;
    }

    /**
     * Generates and returns the HTML form for creating a new record.
     *
     * @return div|false The form container div, or FALSE if no data exists.
     */
    public function do_html_object(): \k1lib\html\div|false {
        if (!empty($this->db_table_data_filtered)) {
            $this->div_container->set_attrib("class", "k1lib-crudlexs-create");

            $this->div_container->set_attrib("class", "k1lib-form-generator " . $this->html_form_column_classes, TRUE);
            $this->div_container->set_attrib("style", "margin:0 auto;", TRUE);

            $html_form = new form();
            $html_form->append_to($this->div_container);

            $form_header = $html_form->append_div("k1lib-form-header mb-1");
            $form_body = $html_form->append_div("k1lib-form-body row");
            $form_footer = $html_form->append_div("k1lib-form-footer");
            $form_footer->set_attrib("style", "margin-top:0.9em;");
            $form_buttons = $html_form->append_div("k1lib-form-buttons");

            $hidden_input = new input("hidden", "k1magic", "123123");
            $hidden_input->append_to($html_form);

            $db_table = $this->db_table;
            $constant_fk_fields = $db_table->get_constant_fields();
            if (!empty($constant_fk_fields)) {

                $row = $form_header->append_div("row");

                foreach ($constant_fk_fields as $field => $field_value) {
                    $field_config = $db_table->get_db_table_field_config($field);
                    while (!empty($field_config['refereced_column_config'])) {
                        $field_config = $field_config['refereced_column_config'];
                    }
                    $table_name = $field_config['table'];
                    $label_value = $db_table->db->get_fk_field_label($table_name, $constant_fk_fields, $db_table->get_db_table_config());

                    if (($label_value !== 0) && ($label_value !== NULL)) {
                        $div_rows = $row->append_div("col-md-6 col-12 k1lib-data-item");

                        $form_group = $div_rows->append_div('form-group');
                        $form_group->append_label($field_config['label'], null, "k1lib-data-item-label");
                        $form_group->append_h6($label_value, "k1lib-data-item-value");
                    }
                }
            }

            $row_number = 0;
            foreach ($this->db_table_data_filtered[1] as $field => $value) {
                if (array_key_exists($field, array_flip($this->fields_to_hide))) {
                    continue;
                }
                $row_number++;
                $row = new label_value_row($this->db_table_data_filtered[0][$field], $value, $row_number);
                $row->append_to($form_body);
            }

            $submit_button = new input("submit", "k1send", creating_strings::$button_submit, "btn icon btn-outline-success btn-sm");
            if ($this->show_cancel_button) {
                if (isset($_GET['cancel-url'])) {
                    $this->back_url = $_GET['cancel-url'];
                }
                $cancel_button = get_link_button($this->back_url, creating_strings::$button_cancel, 'btn-sm');
                $buttons_div = new label_value_row(NULL, "{$cancel_button} {$submit_button}");
            } else {
                $buttons_div = new label_value_row(NULL, "{$submit_button}");
            }

            $buttons_div->append_to($form_buttons);

            return $this->div_container;
        } else {
            return FALSE;
        }
    }

    /**
     * Performs the database insert operation with the validated POST data.
     * NOTE: If the table has multiple keys, auto_increment must be first for redirect to work.
     *
     * @param string $url_to_go Redirect URL (not used in current implementation).
     * @return bool TRUE on success, FALSE on error.
     */
    public function do_insert(): bool {
        $error_data = NULL;
        $sql_query = NULL;
        $this->post_incoming_array = check_all_incomming_vars($this->post_incoming_array);
        $this->inserted_result = $this->db_table->insert_data($this->post_incoming_array, $error_data, $sql_query);
        if ($this->inserted_result !== FALSE) {
            DOM_notification::queue_mesasage(creating_strings::$data_inserted, "success", $this->notifications_div_id);
            $this->inserted = TRUE;
            return TRUE;
        } else {
            if (is_array($error_data) && !empty($error_data)) {
                $this->post_validation_errors = array_merge($this->post_validation_errors, $error_data);
            }
            DOM_notification::queue_mesasage(creating_strings::$data_not_inserted, "warning", $this->notifications_div_id);
            DOM_notification::queue_mesasage(print_r($error_data, TRUE), 'alert', $this->notifications_div_id);
            $this->inserted = FALSE;
            return FALSE;
        }
    }

    /**
     * Gets the primary key values of the newly inserted record.
     *
     * @return array|false Array of key values, or FALSE if not inserted.
     */
    public function get_inserted_keys(): array|false {
        if (($this->inserted) && ($this->inserted_result !== FALSE)) {
            $last_inserted_id = [];
            if (is_numeric($this->inserted_result)) {
                foreach ($this->db_table->get_db_table_config() as $field => $config) {
                    if ($config['extra'] == 'auto_increment') {
                        $last_inserted_id[$field] = $this->inserted_result;
                    }
                }
            }
            $new_keys_array = $this->db_table->db->get_keys_array_from_row_data(
                    array_merge($last_inserted_id, $this->post_incoming_array, $this->db_table->get_constant_fields()),
                    $this->db_table->get_db_table_config()
            );
            return $new_keys_array;
        } else {
            return FALSE;
        }
    }

    /**
     * Gets the complete data of the newly inserted record.
     *
     * @return array|false Array of field values, or FALSE if not inserted.
     */
    public function get_inserted_data(): array|false {
        if (($this->inserted) && ($this->inserted_result !== FALSE)) {
            $last_inserted_id = [];
            if (is_numeric($this->inserted_result)) {
                foreach ($this->db_table->get_db_table_config() as $field => $config) {
                    if ($config['extra'] == 'auto_increment') {
                        $last_inserted_id[$field] = $this->inserted_result;
                    }
                }
            }
            return array_merge($last_inserted_id, $this->post_incoming_array, $this->db_table->get_constant_fields());
        } else {
            return FALSE;
        }
    }

    /**
     * Redirects to specified URL after successful insert.
     *
     * @param string $url_to_go The URL to redirect to. Supports --rowkeys-- and --authcode-- placeholders.
     * @param bool $do_redirect If TRUE, performs header redirect; if FALSE, returns URL.
     * @return string|void Returns URL string if redirect is disabled, void otherwise.
     */
    public function post_insert_redirect($url_to_go = "../", $do_redirect = TRUE): mixed {
        if (($this->inserted) && ($this->inserted_result !== FALSE)) {

            $new_keys_text = $this->db_table->db->table_keys_to_text($this->get_inserted_keys(), $this->db_table->get_db_table_config());

            if (!empty($url_to_go)) {
                $this->set_auth_code($new_keys_text);
                $this->set_auth_code_personal($new_keys_text);
                $url_to_go = str_replace("--rowkeys--", $new_keys_text, $url_to_go);
                $url_to_go = str_replace("--authcode--", $this->get_auth_code(), $url_to_go);
            }
            if ($do_redirect) {
                if ($new_keys_text) {
                    html_header_go($url_to_go);
                    exit;
                } else {
                    html_header_go("../");
                    exit;
                }
                return TRUE;
            } else {
                return $url_to_go;
            }
        } else {
            return "";
        }
    }

    /**
     * Gets the captured POST data array.
     *
     * @return array The POST data.
     */
    function get_post_data(): array {
        return $this->post_incoming_array;
    }

    /**
     * Sets POST data directly.
     *
     * @param array $post_incoming_array The data to set.
     * @return void
     */
    public function set_post_data(array $post_incoming_array): void {
        $this->post_incoming_array = array_merge($this->post_incoming_array, $post_incoming_array);
    }

    /**
     * Sets CSS classes for data column layout.
     *
     * @param string $html_column_classes The CSS classes to apply.
     * @return void
     */
    public function set_html_column_classes($html_column_classes): void {
        $this->html_column_classes = $html_column_classes;
    }

    /**
     * Sets CSS classes for form column layout.
     *
     * @param string $html_form_column_classes The CSS classes to apply.
     * @return void
     */
    public function set_html_form_column_classes($html_form_column_classes): void {
        $this->html_form_column_classes = $html_form_column_classes;
    }

    /**
     * Gets a reference to the incoming POST array.
     *
     * @return array Reference to the POST data array.
     */
    public function &get_post_incoming_array(): array {
        return $this->post_incoming_array;
    }

    /**
     * Gets validation errors from POST processing.
     *
     * @return array Array of validation errors.
     */
    public function get_post_validation_errors(): array {
        return $this->post_validation_errors;
    }

    /**
     * Sets validation errors, optionally appending to existing errors.
     *
     * @param array $errors_array Array of errors to set.
     * @param bool $append_array If TRUE, appends to existing errors; if FALSE, replaces.
     * @return void
     */
    public function set_post_validation_errors(array $errors_array, $append_array = TRUE): void {
        if ($append_array) {
            $this->post_validation_errors = array_merge($this->post_validation_errors, $errors_array);
        } else {
            $this->post_validation_errors = $errors_array;
        }
    }

    /**
     * Sets whether to show the cancel button.
     *
     * @param bool $show_cancel_button Whether to show cancel button.
     * @return void
     */
    public function set_show_cancel_button($show_cancel_button): void {
        $this->show_cancel_button = $show_cancel_button;
    }
}
