<?php

namespace k1lib\crudlexs\object;

use k1lib\common_strings as common_strings;
use k1lib\crudlexs\db_table;
use k1lib\forms\file_uploads as file_uploads;
use k1lib\html\a;
use k1lib\html\img;
use k1lib\notifications\on_DOM as DOM_notification;
use k1lib\html\tag;
use k1lib\K1MAGIC;
use k1lib\session\app_session as app_session;
use k1lib\urlrewrite\url as url;
use function d;
use function k1lib\urlrewrite\get_back_url;
use function k1lib\utils\decimal_to_n36;
use function k1lib\utils\n36_to_decimal;

/**
 * Base class for CRUD objects that work with database table data.
 * Extends base with data loading, filtering, authentication, and field encryption capabilities.
 */
class base_with_data extends base {

    /**
     * Array containing table data where index 0 has field names and indices 1+ have row data.
     * Structure: [0 => [field => label], 1 => [field => value], 2 => [...], ...]
     * @var array
     */
    public $db_table_data = [];

    /**
     * Keys extracted from the database table data for authentication and linking.
     * @var bool|array
     */
    protected $db_table_data_keys = FALSE;

    /**
     * Filtered version of table data after applying field/labels filters.
     * @var bool|array
     */
    public $db_table_data_filtered = FALSE;

    /**
     * Authentication code for the current data set.
     * @var string
     */
    protected $auth_code = null;

    /**
     * Personal authentication code tied to user session.
     * @var string
     */
    protected $auth_code_personal = null;

    /**
     * Flag indicating if link filter has been applied to fields.
     * @var bool
     */
    protected $link_on_field_filter_applied = false;

    /**
     * URL to redirect back to after an action.
     * @var mixed
     */
    protected $back_url;

    /**
     * URL for cancel action.
     * @var mixed
     */
    protected $cancel_url;

    /**
     * URL-encoded text representation of row keys.
     * @var string
     */
    protected $row_keys_text = null;

    /**
     * Array representation of row keys.
     * @var array
     */
    protected $row_keys_array = null;

    /**
     * When TRUE, skips automatic verification of authentication codes.
     * @var bool
     */
    protected $skip_auto_code_verification = FALSE;

    /**
     * When TRUE, blank values are excluded from filters.
     * @var bool
     */
    protected $skip_blanks_on_filters = FALSE;

    /**
     * When TRUE, field names are encrypted for security.
     * @var bool
     */
    protected $do_table_field_name_encrypt = TRUE;

    /**
     * When TRUE, file uploads display as links instead of images.
     * @var bool
     */
    protected $force_file_uploads_as_links = TRUE;

    /**
     * Custom labels for specific fields.
     * @var array
     */
    protected $custom_field_labels = [];

    /**
     * Fields to hide from display.
     * @var array
     */
    protected $fields_to_hide = [];

    /**
     * Object validity state.
     * @var bool
     */
    protected $is_valid = FALSE;

    /**
     * Constructs a base_with_data object with database table and optional row keys.
     * Requires a valid DB Table object for proper initialization.
     *
     * @param db_table $db_table Database table object.
     * @param mixed $row_keys_text Optional row keys text for loading specific record.
     * @param mixed $custom_auth_code Optional custom authentication code.
     */
    public function __construct(db_table $db_table, $row_keys_text = null, $custom_auth_code = null) {
        $this->back_url = get_back_url();

        if (!empty($row_keys_text)) {
            $this->row_keys_text = $row_keys_text;
            if (!$this->skip_auto_code_verification) {
                if (isset($_GET['auth-code']) || !empty($custom_auth_code)) {
                    if (!empty($custom_auth_code)) {
                        $auth_code = $custom_auth_code;
                    } else {
                        $auth_code = $_GET['auth-code'];
                    }
                    $auth_expected = md5(K1MAGIC::get_value() . $this->row_keys_text);
                    $auth_personal_expected = md5(app_session::get_user_hash() . $this->row_keys_text);

                    if (($auth_code === $auth_expected) || ($auth_code === $auth_personal_expected)) {
                        parent::__construct($db_table);
                        $this->auth_code = $auth_expected;
                        $this->auth_code_personal = $auth_personal_expected;
                        $this->row_keys_array = $this->db_table->db->table_url_text_to_keys($this->row_keys_text, $this->db_table->get_db_table_config());
                        $this->db_table->set_query_filter($this->row_keys_array, TRUE);
                        $this->is_valid = TRUE;
                    } else {
                        DOM_notification::queue_mesasage(object_base_strings::$error_bad_auth_code, "alert", $this->notifications_div_id, common_strings::$error);
                        $this->is_valid = FALSE;
                    }
                } else {
                    DOM_notification::queue_mesasage(object_base_strings::$alert_empty_auth_code, "alert", $this->notifications_div_id, common_strings::$alert);
                    $this->is_valid = FALSE;
                }
            } else {
                parent::__construct($db_table);
            }
        } else {
            parent::__construct($db_table);
        }
        $this->set_object_id(get_class($this));
        $this->set_css_class(get_class($this));
    }

    /**
     * Gets the authentication code.
     *
     * @return string The auth code.
     */
    public function get_auth_code(): ?string {
        return $this->auth_code;
    }

    /**
     * Sets the authentication code based on row keys text.
     *
     * @param string $row_keys_text The row keys text.
     */
    public function set_auth_code($row_keys_text): void {
        $this->auth_code = md5(K1MAGIC::get_value() . $row_keys_text);
    }

    /**
     * Gets the personal authentication code.
     *
     * @return string The personal auth code.
     */
    public function get_auth_code_personal(): ?string {
        return $this->auth_code_personal;
    }

    /**
     * Sets the personal authentication code based on row keys text and user hash.
     *
     * @param string $row_keys_text The row keys text.
     */
    public function set_auth_code_personal($row_keys_text): void {
        $this->auth_code_personal = md5(app_session::get_user_hash() . $row_keys_text);
    }

    /**
     * Gets the table field name encryption setting.
     *
     * @return bool TRUE if encryption is enabled.
     */
    public function get_do_table_field_name_encrypt(): bool {
        return $this->do_table_field_name_encrypt;
    }

    /**
     * Sets the table field name encryption flag.
     *
     * @param bool $do_table_field_name_encryp Whether to encrypt field names.
     */
    public function set_do_table_field_name_encrypt($do_table_field_name_encryp = TRUE): void {
        $this->do_table_field_name_encrypt = $do_table_field_name_encryp;
    }

    /**
     * Loads database table data based on current query filters.
     * Data format: [0 => [field => label], 1 => [field => value], ...]
     *
     * @param mixed $show_rule Optional show rule filter.
     * @return bool TRUE on success, FALSE otherwise.
     */
    public function load_db_table_data($show_rule = null) {
        if ($this->is_valid()) {
            if (!empty($show_rule)) {
                $this->db_table->set_db_table_show_rule($show_rule);
            }
            $this->db_table_data = $this->db_table->get_data();
            if ($this->db_table_data) {
                $this->db_table_data_filtered = $this->db_table_data;
                $this->db_table_data_keys = $this->db_table->get_data_keys();
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Simulates database data from a provided array for testing or temporary display.
     * Array must have compatible structure with standard table data format.
     *
     * @param array $data_array The data array to simulate.
     * @return bool TRUE if successful, FALSE otherwise.
     */
    public function simulate_db_data_with_array(array $data_array) {
        if (array_key_exists(0, $data_array)) {
            $headers_count = count($data_array[0]);
            foreach ($data_array as $row => $row_array) {
                if ($row === 0) {
                    continue;
                }
                if (count($row_array) !== $headers_count) {
                    trigger_error(__METHOD__ . " " . object_base_strings::$error_array_not_compatible, E_USER_WARNING);
                    return FALSE;
                }
            }
            $this->db_table_data = $data_array;
            $this->db_table_data_filtered = $data_array;
            return TRUE;
        }
        trigger_error(__METHOD__ . " " . object_base_strings::$error_array_not_compatible, E_USER_WARNING);
        return FALSE;
    }

    /**
     * Simulates database keys from a provided array.
     *
     * @param array $data_array The keys array to simulate.
     * @return bool TRUE if successful, FALSE otherwise.
     */
    public function simulate_db_data_keys_with_array(array $data_array) {
        if (array_key_exists(0, $data_array)) {
            $headers_count = count($data_array[0]);
            foreach ($data_array as $row => $row_array) {
                if ($row === 0) {
                    continue;
                }
                if (count($row_array) !== $headers_count) {
                    trigger_error(__METHOD__ . " " . object_base_strings::$error_array_not_compatible, E_USER_WARNING);
                    return FALSE;
                }
            }
            $this->db_table_data_keys = $data_array;
            return TRUE;
        }
        trigger_error(__METHOD__ . " " . object_base_strings::$error_array_not_compatible, E_USER_WARNING);
        return FALSE;
    }

    /**
     * Applies label filter to convert field names to configured labels.
     *
     * @return bool TRUE if filter was applied, FALSE otherwise.
     */
    public function apply_label_filter() {
        if (empty($this->db_table_data) || !is_array($this->db_table_data)) {
            return FALSE;
        } else {
            $db_table_config = $this->db_table->get_db_table_config();
            if (isset($this->db_table_data[0]) && (count($this->db_table_data[0]) > 0)) {
                foreach ($this->db_table_data[0] as $index => $field_name) {
                    if (isset($db_table_config[$field_name]['label'])) {
                        $this->db_table_data_filtered[0][$index] = $db_table_config[$field_name]['label'];
                    } else {
                        if (isset($this->custom_field_labels[$field_name])) {
                            $this->db_table_data_filtered[0][$index] = $this->custom_field_labels[$field_name];
                        } else {
                            $this->db_table_data_filtered[0][$index] = $field_name;
                        }
                    }
                }
            } else {
                return FALSE;
            }
            return TRUE;
        }
    }

    /**
     * Applies field label filter to replace foreign key values with their display labels.
     *
     * @param array $apply_to Fields to apply filter to. Empty array means all fields.
     * @return bool TRUE if filter was applied, FALSE otherwise.
     */
    public function apply_field_label_filter(array $apply_to = []) {
        if ($this->get_state()) {
            if (empty($this->db_table_data) || !is_array($this->db_table_data)) {
                trigger_error(__METHOD__ . " " . object_base_strings::$error_no_table_data, E_USER_WARNING);
                return FALSE;
            } else {
                $table_config_array = $this->db_table->get_db_table_config();
                foreach ($this->db_table_data as $index => $row_data) {
                    if ($index === 0) {
                        continue;
                    }
                    foreach ($row_data as $field => $value) {
                        if (!empty($apply_to) && !array_key_exists($field, array_flip($apply_to))) {
                            continue;
                        }
                        if (!empty($table_config_array[$field]['refereced_column_config'])) {
                            $refereced_column_config = $table_config_array[$field]['refereced_column_config'];
                            while (!empty($refereced_column_config['refereced_column_config'])) {
                                $refereced_column_config = $refereced_column_config['refereced_column_config'];
                            }
                            $fk_table = $refereced_column_config['table'];
                            $fk_label_field = $this->db_table->db->get_fk_field_label($fk_table, [$field => $value], $table_config_array, $refereced_column_config['key']);
                            if (!empty($fk_label_field)) {
                                if (is_object($this->db_table_data_filtered[$index][$field])) {
                                    $this->db_table_data_filtered[$index][$field]->set_value($fk_label_field);
                                } else {
                                    $this->db_table_data_filtered[$index][$field] = $fk_label_field;
                                }
                            }
                        }
                    }
                }

                return TRUE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Applies file upload filter to convert file references to appropriate HTML elements.
     *
     * @return bool TRUE if filter was applied, FALSE otherwise.
     */
    public function apply_file_uploads_filter() {
        if ($this->get_state()) {
            if (empty($this->db_table_data) || !is_array($this->db_table_data)) {
                trigger_error(__METHOD__ . " " . object_base_strings::$error_no_table_data, E_USER_WARNING);
                return FALSE;
            } else {
                $table_config_array = $this->db_table->get_db_table_config();
                $file_upload_fields = [];
                foreach ($table_config_array as $field => $options) {
                    if ($options['validation'] == 'file-upload') {
                        $file_upload_fields[$field] = $options['file-type'];
                        $file_upload_table[$field] = $options['table'];
                    }
                }
                if (!empty($file_upload_fields)) {
                    foreach ($file_upload_fields as $field => $file_type) {
                        switch (substr($file_type, 0, 5)) {
                            case "image":
                                $img_tag = new img(file_uploads::get_uploads_url($options['table']) . "--fieldvalue--");
                                $img_tag->set_attrib("onClick", "window.open(this.getAttribute('src'),'imgWindow', 'height=1024,width=768,toolbar=0,location=0,menubar=0');", TRUE);
                                $img_tag->set_attrib("class", "k1lib-data-img", TRUE);

                                return $this->apply_html_tag_on_field_filter($img_tag, array_keys($file_upload_fields));

                            default:
                                $link_tag = new a(url::do_url(file_uploads::get_uploads_url() . "{$file_upload_table[$field]}/--fieldvalue--"), "--fieldvalue--", "_blank");
                                $link_tag->set_attrib("class", "k1lib-data-link", TRUE);
                                return $this->apply_html_tag_on_field_filter($link_tag, array_keys($file_upload_fields));
                        }
                    }
                }
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Applies a link filter to specified fields, creating clickable URLs.
     *
     * @param string $link_to_apply URL pattern with placeholders like --rowkeys--, --fieldvalue--.
     * @param mixed $fields_to_change Fields to apply link to (array, USE_KEY_FIELDS, USE_ALL_FIELDS, etc.).
     * @param mixed $custom_field_to_use_value Custom field value to include in URL.
     * @param string $href_target Link target attribute.
     * @return bool TRUE if filter was applied, FALSE otherwise.
     */
    public function apply_link_on_field_filter($link_to_apply, $fields_to_change = null, $custom_field_to_use_value = null, $href_target = null) {
        if ($this->get_state()) {
            $this->link_on_field_filter_applied = true;
            $a_tag = new a(url::do_url($link_to_apply), "", $href_target);
            $a_tag->set_attrib("class", "k1lib-link-filter", TRUE);
            if (empty($fields_to_change)) {
                $fields_to_change = base::USE_KEY_FIELDS;
            }
            return $this->apply_html_tag_on_field_filter($a_tag, $fields_to_change, $custom_field_to_use_value);
        } else {
            return FALSE;
        }
    }

    /**
     * Applies an HTML tag to filtered data fields with placeholder replacement.
     * Supports placeholders: --rowkeys--, --fieldvalue--, --authcode--, --customfieldvalue--, --fieldauthcode--
     *
     * @param tag $tag_object The HTML tag object to apply.
     * @param mixed $fields_to_change Fields to modify (array, USE_KEY_FIELDS, USE_ALL_FIELDS, USE_LABEL_FIELDS).
     * @param mixed $custom_field_to_use_value Custom field value to use in URL.
     * @return bool TRUE if applied, FALSE otherwise.
     */
    public function apply_html_tag_on_field_filter(tag $tag_object, $fields_to_change = base::USE_KEY_FIELDS, $custom_field_to_use_value = null) {
        if ($this->get_state()) {
            if (empty($this->db_table_data) || !is_array($this->db_table_data)) {
                return FALSE;
            } else {
                if ($fields_to_change === base::USE_KEY_FIELDS) {
                    $fields_to_change = $this->db_table->get_db_table_keys_array($this->db_table->get_db_table_config());
                } elseif ($fields_to_change === base::USE_ALL_FIELDS) {
                    $fields_to_change = $this->db_table_data[0];
                } elseif ($fields_to_change === base::USE_LABEL_FIELDS) {
                    $fields_to_change = $this->db_table->get_db_table_label_fields($this->db_table->get_db_table_config());
                    if (empty($fields_to_change)) {
                        $fields_to_change = $this->db_table->get_db_table_keys_array($this->db_table->get_db_table_config());
                    }
                } elseif (empty($fields_to_change)) {
                    $fields_to_change = $this->db_table_data[0];
                } else {
                    if (!is_array($fields_to_change) && is_string($fields_to_change)) {
                        $fields_to_change = array($fields_to_change);
                    }
                }
                $table_constant_fields = $this->db_table->get_constant_fields();
                if (!empty($fields_to_change)) {
                    foreach ($fields_to_change as $field_to_change) {
                        foreach ($this->db_table_data_filtered as $index => $row_data) {
                            if ($index === 0) {
                                continue;
                            }
                            if (!array_key_exists($field_to_change, $row_data)) {
                                trigger_error(__METHOD__ . "The field to change ($field_to_change) do no exist ", E_USER_NOTICE);
                                continue;
                            } else {
                                $tag_object_original = clone $tag_object;

                                $custom_field_to_use_value_original = $custom_field_to_use_value;

                                if ($this->skip_blanks_on_filters && ($row_data[$field_to_change] === NULL || $row_data[$field_to_change] === '')) {
                                    continue;
                                }

                                $tag_object->set_value($row_data[$field_to_change]);

                                if (is_object($tag_object)) {
                                    $a_tags = [];
                                    $tag_value = null;
                                    if (get_class($tag_object) == "k1lib\html\a") {
                                        $tag_href = $tag_object->get_attribute("href");
                                        $tag_value = $tag_object->get_value();
                                    } elseif (get_class($tag_object) == "k1lib\html\img") {
                                        $tag_href = $tag_object->get_attribute("src");
                                        $tag_value = $tag_object->get_attribute("alt");
                                    } else {
                                        $a_tags = $tag_object->get_elements_by_tag("a");
                                        if (!empty($a_tags) && count($a_tags) === 1) {
                                            $tag_href = $a_tags[0]->get_attribute("href");
                                            $tag_value = $a_tags[0]->get_value();
                                        } else {
                                            $tag_href = NULL;
                                        }
                                    }

                                    if (!empty($this->db_table_data_keys) && !empty($tag_href)) {
                                        if (is_array($custom_field_to_use_value)) {
                                            foreach ($custom_field_to_use_value as $key => $field_value) {
                                                if (isset($row_data[$field_value])) {
                                                    $custom_field_to_use_value[$key] = $this->db_table_data[$index][$field_value];
                                                } else
                                                if (isset($table_constant_fields[$field_value])) {
                                                    $custom_field_to_use_value[$key] = $table_constant_fields[$field_value];
                                                } else
                                                if (isset($this->db_table_data_keys[$index][$field_value])) {
                                                    $custom_field_to_use_value[$key] = $this->db_table_data_keys[$index][$field_value];
                                                } else {
                                                    $custom_field_to_use_value[$key] = NULL;
                                                }
                                            }
                                            $custom_field_to_use_value = implode("--", $custom_field_to_use_value);
                                        }

                                        $key_array_text = $this->db_table->db->table_keys_to_text($this->db_table_data_keys[$index], $this->db_table->get_db_table_config());
                                        $auth_code = md5(K1MAGIC::get_value() . $key_array_text);

                                        $tag_href = str_replace("--rowkeys--", urlencode($key_array_text), $tag_href);
                                        $tag_href = str_replace("--fieldvalue--", rawurlencode($row_data[$field_to_change]), $tag_href);
                                        if (!empty($custom_field_to_use_value)) {
                                            $actual_custom_field_value = str_replace("--fieldvalue--", urlencode($row_data[$field_to_change]), $custom_field_to_use_value);
                                            $tag_href = str_replace("--customfieldvalue--", urlencode($actual_custom_field_value), $tag_href);
                                            $tag_href = str_replace("--fieldauthcode--", md5(K1MAGIC::get_value() . (($actual_custom_field_value) ? $actual_custom_field_value : $row_data[$field_to_change])), $tag_href);
                                        } else {
                                            $actual_custom_field_value = null;
                                        }
                                        $tag_href = str_replace("--authcode--", $auth_code, $tag_href);

                                        if (!empty($tag_value)) {
                                            $tag_value = str_replace("--rowkeys--", $key_array_text, $tag_value);
                                            $tag_value = str_replace("--fieldvalue--", urlencode($row_data[$field_to_change]), $tag_value);
                                            $tag_value = str_replace("--authcode--", $auth_code, $tag_value);
                                            if (!empty($actual_custom_field_value)) {
                                                $tag_value = str_replace("--customfieldvalue--", $actual_custom_field_value, $tag_value);
                                            }
                                            $tag_value = str_replace("--fieldauthcode--", md5(K1MAGIC::get_value() . (!empty($actual_custom_field_value) ? $actual_custom_field_value : $row_data[$field_to_change])), $tag_value);
                                        }
                                        if (get_class($tag_object) == "k1lib\html\a") {
                                            $tag_object->set_attrib("href", $tag_href);
                                            $tag_object->set_value($tag_value);
                                        }
                                        if (get_class($tag_object) == "k1lib\html\img") {
                                            $tag_object->set_attrib("src", $tag_href);
                                            $tag_object->set_style("max-height:150px; max-width:150px");
                                        }
                                        foreach ($a_tags as $a_tag) {
                                            $a_tag->set_attrib("href", $tag_href);
                                            $a_tag->set_value($tag_value);
                                        }
                                    }
                                } else {
                                    trigger_error("Not a HTML_TAG Object", E_USER_WARNING);
                                }
                                $this->db_table_data_filtered[$index][$field_to_change] = $tag_object;
                                unset($tag_object);
                                $tag_object = clone $tag_object_original;
                                $custom_field_to_use_value = $custom_field_to_use_value_original;
                            }
                        }
                    }
                }
                return TRUE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Checks if link filter has been applied.
     *
     * @return bool TRUE if applied, FALSE otherwise.
     */
    public function get_link_on_field_filter_applied(): bool {
        return $this->link_on_field_filter_applied;
    }

    /**
     * Gets the back URL.
     *
     * @return string The back URL.
     */
    public function get_back_url(): mixed {
        return $this->back_url;
    }

    /**
     * Sets the back URL.
     *
     * @param string $back_url The URL to set.
     */
    public function set_back_url($back_url): void {
        $this->back_url = $back_url;
    }

    /**
     * Gets row keys text if available.
     *
     * @return string|false Row keys text or FALSE if not set.
     */
    function get_row_keys_text(): string|false {
        if (!empty($this->row_keys_text)) {
            return $this->row_keys_text;
        } else {
            return FALSE;
        }
    }

    /**
     * Gets row keys array if available.
     *
     * @return array|false Row keys array or FALSE if not set.
     */
    function get_row_keys_array(): array|false {
        if (!empty($this->row_keys_array)) {
            return $this->row_keys_array;
        } else {
            return FALSE;
        }
    }

    /**
     * Encrypts a field name based on its position and session random value.
     *
     * @param string $field_name The field name to encrypt.
     * @return string The encrypted field name or alias if configured.
     */
    public function encrypt_field_name($field_name) {
        if (isset($_SESSION['CRUDLEXS-RND']) && !empty($_SESSION['CRUDLEXS-RND'])) {
            $rnd = $_SESSION['CRUDLEXS-RND'];
        } else {
            $rnd = rand(5000, 10000);
            $_SESSION['CRUDLEXS-RND'] = $rnd;
        }
        if ($this->do_table_field_name_encrypt) {
            $field_pos = 0;
            if (key_exists($field_name, $this->db_table->get_db_table_config())) {
                foreach ($this->db_table->get_db_table_config() as $field => $config) {
                    if ($field == $field_name) {
                        if ($config['alias']) {
                            return $config['alias'];
                        }
                        break;
                    }
                    $field_pos++;
                }
                $new_field_name = "k1_" . decimal_to_n36($field_pos + $rnd);
                return $new_field_name;
            } else {
                return $field_name;
            }
        } else {
            return $field_name;
        }
    }

    /**
     * Encrypts all field names in a data array.
     *
     * @param array $data_array The data array with field names as keys.
     * @return array The array with encrypted field names.
     */
    public function encrypt_field_names($data_array) {
        $encoded_data_array = [];
        foreach ($data_array as $field => $value) {
            $encoded_data_array[$this->encrypt_field_name($field)] = $value;
        }
        return $encoded_data_array;
    }

    /**
     * Decrypts an encrypted field name back to original.
     *
     * @param string $encrypted_name The encrypted field name.
     * @return string The original field name.
     */
    public function decrypt_field_name($encrypted_name) {
        if (strstr($encrypted_name, "k1_") !== FALSE) {
            list($prefix, $n36_number) = explode("_", $encrypted_name);
            if (isset($_SESSION['CRUDLEXS-RND']) && !empty($_SESSION['CRUDLEXS-RND'])) {
                $rnd = $_SESSION['CRUDLEXS-RND'];
            } else {
                d($_SESSION);

                trigger_error(__METHOD__ . ' ' . object_base_strings::$error_no_session_random, E_USER_ERROR);
            }
            $field_position = n36_to_decimal($n36_number) - $rnd;
            $fields_from_table_config = array_keys($this->db_table->get_db_table_config());
            return $fields_from_table_config[$field_position];
        } else {
            foreach ($this->db_table->get_db_table_config() as $field => $config) {
                if ($config['alias'] == $encrypted_name) {
                    return $field;
                }
            }
            return $encrypted_name;
        }
    }

    /**
     * Decrypts all field names in a data array.
     *
     * @param array $data_array The data array with encrypted field names.
     * @return array The array with original field names.
     */
    public function decrypt_field_names($data_array) {
        $decoded_data_array = [];
        foreach ($data_array as $field => $value) {
            $decoded_data_array[$this->decrypt_field_name($field)] = $value;
        }
        return $decoded_data_array;
    }

    /**
     * Gets label field value from a specific row.
     *
     * @param int $row The row index to get label from.
     * @return string|null The label value or NULL if not found.
     */
    public function get_labels_from_data($row = 1): string|null|false {
        if ($this->db_table_data) {
            $data_label = $this->db_table->db->get_db_table_label_fields_from_row($this->db_table_data_filtered[$row], $this->db_table->get_db_table_config());
            if (!empty($data_label)) {
                return $data_label;
            } else {
                return NULL;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Removes label fields from filtered data.
     *
     * @param int $row The row index to remove labels from.
     */
    public function remove_labels_from_data_filtered($row = 1): void {
        if ($this->db_table_data) {
            $label_fields_array = $this->db_table->get_db_table_label_fields($this->db_table->get_db_table_config());
            foreach ($label_fields_array as $field) {
                unset($this->db_table_data_filtered[$row][$field]);
            }
        }
    }

    /**
     * Gets the raw table data.
     *
     * @return array The table data array.
     */
    public function get_db_table_data(): array {
        return $this->db_table_data;
    }

    /**
     * Gets the table data keys.
     *
     * @return array|bool The keys array or FALSE if not set.
     */
    public function get_db_table_data_keys(): array|bool {
        return $this->db_table_data_keys;
    }

    /**
     * Gets the filtered table data.
     *
     * @return array|bool The filtered data array or FALSE if not set.
     */
    public function get_db_table_data_filtered(): array|bool {
        return $this->db_table_data_filtered;
    }

    /**
     * Gets custom field labels configuration.
     *
     * @return array Custom field labels array.
     */
    function get_custom_field_labels(): array {
        return $this->custom_field_labels;
    }

    /**
     * Sets custom field labels configuration.
     *
     * @param array $custom_field_labels The labels to set.
     */
    function set_custom_field_labels(array $custom_field_labels): void {
        $this->custom_field_labels = $custom_field_labels;
    }

    /**
     * Gets fields to hide configuration.
     *
     * @return array Fields to hide array.
     */
    public function get_fields_to_hide(): array {
        return $this->fields_to_hide;
    }

    /**
     * Sets fields to hide configuration.
     *
     * @param array $fields_to_hide The fields to hide.
     */
    public function set_fields_to_hide(array $fields_to_hide): void {
        $this->fields_to_hide = $fields_to_hide;
    }
}
