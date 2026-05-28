<?php

namespace k1lib\crudlexs\object;

use k1lib\common_strings;
use k1lib\crudlexs\db_table;
use k1lib\db\security\db_table_aliases;
use k1lib\html\a;
use k1lib\html\bootstrap\components\input_text_with_icon;
use k1lib\html\button;
use k1lib\html\div;
use k1lib\html\DOM;
use k1lib\html\input;
use k1lib\html\label;
use k1lib\html\script;
use k1lib\html\select;
use k1lib\html\span;
use k1lib\html\textarea;
use k1lib\session\app_session;
use k1lib\urlrewrite\url as url;
use const k1app\K1APP_URL;
use const k1app\template\mazer\TPL_URL;
use const k1lib\K1LIB_BASE_PATH;
use function d;
use function k1lib\urlrewrite\get_back_url;

/**
 * Helper class for generating HTML input elements based on field configurations.
 * Provides static methods for creating inputs for various field types including
 * enums, text, files, passwords, booleans, and foreign key searches.
 */
class input_helper {

    /**
     * Whether to enable foreign key search tool.
     * @var bool
     */
    static $do_fk_search_tool = TRUE;

    /**
     * Whether the FK search tool JavaScript has been loaded.
     * @var bool
     */
    static $fk_search_tool_js_loaded = FALSE;

    /**
     * URL for searching foreign key data.
     * @var string
     */
    static $url_to_search_fk_data = K1APP_URL . "core/tools/select_row_keys/";

    /**
     * URL for sending selected row keys back.
     * @var string
     */
    static $url_to_send_row_keys_fk_data = K1APP_URL . "core/tools/send_row_keys/";

    /**
     * Main CSS file to include with editors.
     * @var string
     */
    static $main_css = "";

    /**
     * Foreign key fields to skip in search tool.
     * @var array
     */
    static private $fk_fields_to_skip = [];

    /**
     * True label for boolean fields.
     * @var string
     */
    static public $boolean_true = NULL;

    /**
     * False label for boolean fields.
     * @var string
     */
    static public $boolean_false = NULL;

    /**
     * Generates a password input group with current/new/confirm fields.
     *
     * @param creating $crudlex_obj The CRUD object instance.
     * @param string $field The field name.
     * @param string $case Either "create" or "update".
     * @return div Container with password inputs.
     */
    static function password_type(creating $crudlex_obj, $field, $case = "create") {
        $field_encrypted = $crudlex_obj->encrypt_field_name($field) . "_password";
        $tag_id = $crudlex_obj->encrypt_field_name($field) . "-reveal";
        $crudlex_obj->db_table_data_filtered[1][$field] = null;

        $div_continer = new div();

        $input_tag_new = new input("password", $field_encrypted . "_new", NULL, "k1lib-input-insert form-control");
        $input_tag_confirm = new input("password", $field_encrypted . "_confirm", NULL, "k1lib-input-insert form-control");

        if ($case == "create") {
            $div_continer->link_value_obj($input_tag_new);
        } elseif ($case == "update") {
            $input_tag_current = new input("password", $field_encrypted . "_current", NULL, "k1lib-input-insert form-control");
            $input_tag_current->set_attrib("placeholder", input_helper_strings::$password_current);
            $div_continer->append_div()->append_child($input_tag_current);
            $div_continer->link_value_obj($input_tag_current);
        }
        $input_tag_new->set_attrib("placeholder", input_helper_strings::$password_new);
        $input_tag_confirm->set_attrib("placeholder", input_helper_strings::$password_confirm);

        $div_continer->append_div()->append_child($input_tag_new);
        $div_continer->append_div()->append_child($input_tag_confirm);

        return $div_continer;
    }

    /**
     * Generates a select element for enum field types.
     *
     * @param creating $crudlex_obj The CRUD object instance.
     * @param string $field The field name.
     * @return select The select element with enum options.
     */
    static function enum_type(creating $crudlex_obj, $field) {
        $user_rol = app_session::get_user_level();
        $enum_data = $crudlex_obj->db_table->get_enum_options($field, $user_rol);
        $input_tag = new select($field);
        $input_tag->append_option("", input_helper_strings::$select_choose_option);

        foreach ($enum_data as $index => $value) {
            if ($crudlex_obj->db_table_data[1][$field] == $value) {
                $selected = TRUE;
            } else {
                $selected = FALSE;
            }
            $input_tag->append_option($index, $value, $selected);
        }
        return $input_tag;
    }

    /**
     * Generates a textarea input, optionally with TinyMCE editor.
     *
     * @param creating $crudlex_obj The CRUD object instance.
     * @param string $field The field name.
     * @param bool $load_tinymce Whether to load TinyMCE editor.
     * @return textarea The textarea element.
     */
    static function text_type(creating $crudlex_obj, $field, $load_tinymce = TRUE) {
        $field_encrypted = $crudlex_obj->encrypt_field_name($field);

        if (!empty(self::$main_css)) {
            $css_option = "content_css: ['" . self::$main_css . "?' + new Date().getTime()],";
        } else {
            $css_option = "";
        }
        $input_tag = new textarea($field_encrypted);
        $input_tag->set_attrib("rows", 5);

        DOM::html()->body()->append_child_tail(new script(TPL_URL . "assets/extensions/tinymce/tinymce.min.js"));
        DOM::html()->body()->append_child_tail(new script(TPL_URL . "assets/static/js/pages/tinymce.js"));

        if ($load_tinymce) {
            $html_script = "document.addEventListener('DOMContentLoaded', () => {"
                    . "tinymce.init({ "
                    . "selector: '#$field_encrypted',"
                    . "height: 300,"
                    . "menubar: false,"
                    . $css_option
                    . "body_class: 'html-editor',"
                    . "toolbar: 'insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent',"
                    . ""
                    . "});"
                    . "})";
            $script = (new script())->set_value($html_script);
            $input_tag->post_code($script->generate());
        }

        return $input_tag;
    }

    /**
     * Generates a file upload input with optional delete link.
     *
     * @param creating $crudlex_obj The CRUD object instance.
     * @param string $field The field name.
     * @return div|input Container with file input or delete link.
     */
    static function file_upload(creating $crudlex_obj, $field) {
        $field_encrypted = $crudlex_obj->encrypt_field_name($field);

        $input_tag = new input("file", $field_encrypted, "", "k1lib-file-upload form-control");
        if (isset($crudlex_obj->db_table_data[1][$field]['name']) || empty($crudlex_obj->db_table_data[1][$field])) {
            return $input_tag;
        } else {
            $delete_file_link = new a("./unlink-uploaded-file/" . $field_encrypted . "/?auth-code=--authcode--&back-url=" . urlencode(get_back_url()), input_helper_strings::$button_remove);
            $div_container = new div(null, "img-delete-link");
            $div_container->append_child($input_tag);
            $div_container->append_child($delete_file_link);
            $div_container->link_value_obj($input_tag);

            return $div_container;
        }
    }

    /**
     * Generates radio button inputs for boolean fields.
     *
     * @param creating $crudlex_obj The CRUD object instance.
     * @param string $field The field name.
     * @return div Container with radio button pair.
     */
    static function boolean_type(creating $crudlex_obj, $field) {
        if (self::$boolean_true === NULL) {
            self::$boolean_true = common_strings::$yes;
        }
        if (self::$boolean_false === NULL) {
            self::$boolean_false = common_strings::$no;
        }

        $field_encrypted = $crudlex_obj->encrypt_field_name($field);

        $input_div = new div();
        $input_div->link_value_obj(new span('hidden'));

        $input_yes = new input("radio", $field_encrypted, '1');
        $label_yes = new label(self::$boolean_true, $field_encrypted);
        $input_yes->post_code($label_yes->generate());
        $input_yes->append_to($input_div);

        if ($crudlex_obj->db_table_data[1][$field] == '1') {
            $input_yes->set_attrib('checked', TRUE);
        }

        $input_no = new input("radio", $field_encrypted, '0');
        $label_no = new label(self::$boolean_false, $field_encrypted);
        $input_no->post_code($label_no->generate());
        $input_no->append_to($input_div);

        if ($crudlex_obj->db_table_data[1][$field] == '0') {
            $input_no->set_attrib('checked', TRUE);
        }

        return $input_div;
    }

    /**
     * Generates appropriate input based on field configuration.
     * Handles FK searches, date pickers, and standard inputs.
     *
     * @param creating $crudlex_obj The CRUD object instance.
     * @param string $field The field name.
     * @return mixed The input element appropriate for the field type.
     */
    static function default_type(creating $crudlex_obj, $field) {
        $field_encrypted = $crudlex_obj->encrypt_field_name($field);
        if ((!empty($crudlex_obj->db_table->get_field_config($field, 'refereced_table_name')) && self::$do_fk_search_tool) && (array_search($field, self::$fk_fields_to_skip) === FALSE)) {
            $refereced_column_config = $crudlex_obj->db_table->get_field_config($field, 'refereced_column_config');
            $this_table = $crudlex_obj->db_table->get_db_table_name();
            $this_table_alias = db_table_aliases::encode($this_table);

            $fk_table = $refereced_column_config['table'];
            $fk_table_alias = db_table_aliases::encode($fk_table);

            $fk_db_table = new db_table($crudlex_obj->db_table->db, $fk_table);
            $fk_db_table_config = $fk_db_table->get_db_table_config();

            $static_values = $crudlex_obj->db_table->get_constant_fields();

            $static_values_filtered = \k1lib\common\clean_array_with_guide($static_values, $fk_db_table_config);

            $static_values_enconded = $crudlex_obj->encrypt_field_names($static_values_filtered);

            if (DOM::html()->body() && !self::$fk_search_tool_js_loaded) {
                $js_file = K1LIB_BASE_PATH . '/static/js/crudlexs.js';
                if (file_exists($js_file)) {
                    $js_content = file_get_contents($js_file);
                    $js_script = new script();
                    $js_script->set_value($js_content);

                    $js_script->append_to(DOM::html()->body());
                    self::$fk_search_tool_js_loaded = TRUE;
                } else {
                    d($js_file);
                }
            }

            $div_input_group = new div("input-group");

            $input_tag = new input("text", $field_encrypted, NULL, "k1lib-input-insert input-group-field form-control");
            if (!empty($crudlex_obj->db_table->get_field_config($field, 'placeholder'))) {
                $input_tag->set_attrib("placeholder", $crudlex_obj->db_table->get_field_config($field, 'placeholder'));
            } else {
                $input_tag->set_attrib("placeholder", input_helper_strings::$input_fk_placeholder);
            }
            $input_tag->set_attrib("k1lib-data-group-" . $crudlex_obj->db_table->get_field_config($field, 'refereced_table_name'), TRUE);
            $input_tag->append_to($div_input_group);

            $search_button = new button(null, "btn btn-outline-secondary fk-button");
            $search_button->append_i(null, 'bi bi-search');

            $url_params = array_merge(
                    [
                        "back-url" => $_SERVER['REQUEST_URI']
                    ],
                    $static_values_enconded,
            );
            $field_config = $crudlex_obj->db_table->get_field_config($field, 'key');
            $field_config_fk = $crudlex_obj->db_table->get_field_config($field, 'refereced_column_config');
            if (
                    ($field_config == 'uni') || ($field_config == 'mul')
            ) {
                if ($field_config_fk['key'] == 'uni') {
                    $url_params['caller-field'] = $field_encrypted;
                }
            }

            $url_to_search_fk_data = url::do_url(self::$url_to_search_fk_data . "{$fk_table_alias}/list/$this_table_alias/", $url_params);
            $search_button->set_attrib("onclick", "javascript:use_select_row_keys(this.form,'{$url_to_search_fk_data}')");

            $search_button->append_to($div_input_group);

            $div_input_group->link_value_obj($input_tag);
            return $div_input_group;
        } elseif (strstr("date,date-past,date-future", $crudlex_obj->db_table->get_field_config($field, 'validation')) !== FALSE) {
            DOM::html()->head()->link_css(TPL_URL . "assets/extensions/flatpickr/flatpickr.min.css");
            DOM::html()->body()->append_child_head(new script(TPL_URL . "assets/extensions/flatpickr/flatpickr.min.js"));
            DOM::html()->body()->append_child_head(new script(TPL_URL . "assets/static/js/pages/date-picker.js"));

            $div_input_group = new div("input-group");
            $span_icon = $div_input_group->append_span('input-group-text');
            $span_icon->append_i(null, 'bi bi-calendar');

            $input_tag = new input("text", $field_encrypted, NULL, "k1lib-input-insert form-control flatpickr-crudlexs");
            $input_tag->set_attrib("placeholder", input_helper_strings::$input_date_placeholder);
            $input_tag->append_to($div_input_group);

            $div_input_group->link_value_obj($input_tag);
            return $div_input_group;
        } else {
            $icon = $crudlex_obj->db_table->get_field_config($field, 'icon');
            if (empty($icon)) {
                $input_tag = new input("text", $field_encrypted, NULL, "k1lib-input-insert form-control");
                $input_tag->set_attrib("placeholder", $crudlex_obj->db_table->get_field_config($field, 'placeholder'));
                return $input_tag;
            } else {
                $input_icon = new input_text_with_icon($field_encrypted, NULL, $icon);
                $input_tag = $input_icon->input();
                $input_tag->set_attrib("placeholder", $crudlex_obj->db_table->get_field_config($field, 'placeholder'));
                return $input_icon;
            }
        }
    }

    /**
     * Gets whether FK search tool is enabled.
     *
     * @return bool TRUE if enabled, FALSE otherwise.
     */
    public static function get_do_fk_search_tool() {
        return self::$do_fk_search_tool;
    }

    /**
     * Gets fields excluded from FK search.
     *
     * @return array Array of field names to skip.
     */
    public static function get_fk_fields_to_skip() {
        return self::$fk_fields_to_skip;
    }

    /**
     * Sets whether FK search tool is enabled.
     *
     * @param bool $do_fk_search_tool Whether to enable FK search.
     * @return void
     */
    public static function set_do_fk_search_tool($do_fk_search_tool) {
        self::$do_fk_search_tool = $do_fk_search_tool;
    }

    /**
     * Sets fields to exclude from FK search.
     *
     * @param array $fk_fields_to_skip Array of field names.
     * @return void
     */
    public static function set_fk_fields_to_skip(array $fk_fields_to_skip) {
        self::$fk_fields_to_skip = $fk_fields_to_skip;
    }
}
