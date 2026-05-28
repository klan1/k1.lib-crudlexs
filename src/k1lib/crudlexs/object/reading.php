<?php

namespace k1lib\crudlexs\object;

use k1lib\common_strings;
use k1lib\crudlexs\board\read;
use k1lib\db\security\db_table_aliases;
use k1lib\html\div;
use k1lib\html\h3;
use k1lib\notifications\on_DOM as DOM_notification;

/**
 * Reading object for displaying a single database record in read mode.
 * Renders field values as read-only HTML elements within a structured layout.
 */
class reading extends base_with_data implements base_interface {

    /**
     * Reference to the parent board object.
     * @var read
     */
    public read $parent_board;

    /**
     * CSS classes for HTML column layout.
     * @var string
     */
    private $html_column_classes = "col-md-6 col-12";

    /**
     * Constructs a reading object for displaying a single record.
     *
     * @param mixed $db_table The database table object.
     * @param string $row_keys_text URL-encoded row keys text identifying the record.
     * @param string $custom_auth_code Optional custom authentication code.
     */
    public function __construct($db_table, $row_keys_text, $custom_auth_code = "") {
        if (!empty($row_keys_text)) {
            parent::__construct($db_table, $row_keys_text, $custom_auth_code);
        } else {
            DOM_notification::queue_mesasage(object_base_strings::$error_no_row_keys_text, "alert", $this->notifications_div_id, common_strings::$error);
        }

        $this->skip_blanks_on_filters = TRUE;
    }

    /**
     * Generates and returns the HTML representation of the reading object.
     * Creates a responsive grid layout displaying field labels and values.
     *
     * @return div|false Returns the HTML container div, or FALSE if no data exists.
     */
    public function do_html_object(): \k1lib\html\div|false {
        if ($this->db_table_data) {
            $this->div_container->set_attrib("class", "row k1lib-crudlexs-" . $this->css_class);
            $this->div_container->set_attrib("id", $this->object_id);

            $table_alias = db_table_aliases::encode($this->db_table->get_db_table_name());

            $data_group = new div("k1lib-data-group");
            $data_group->set_id("{$table_alias}-fields");

            $data_group->append_to($this->div_container);
            $text_fields_div = new div("row");

            $data_label = $this->get_labels_from_data(1);
            if (!empty($data_label)) {
                $this->remove_labels_from_data_filtered();
                $this->parent_board->set_board_name($data_label);
            }
            $labels = $this->db_table_data_filtered[0];
            $values = $this->db_table_data_filtered[1];
            $row = $data_group->append_div("row");

            foreach ($values as $field => $value) {
                if (array_search($field, $this->fields_to_hide) !== FALSE) {
                    continue;
                }
                if (($value !== 0) && ($value !== NULL)) {
                    $field_type = $this->db_table->get_field_config($field, 'type');
                    $field_alias = $this->db_table->get_field_config($field, 'alias');
                    if ($field_type == 'text') {
                        $div_rows = $text_fields_div->append_div("col-md-6 col-12 k1lib-data-item");
                    } else {
                        $div_rows = $row->append_div($this->html_column_classes . " k1lib-data-item");
                    }
                    if (!empty($field_alias)) {
                        $div_rows->set_id("{$field_alias}-row");
                    }
                    $form_group = $div_rows->append_div('form-group');
                    $label = $form_group->append_label($labels[$field], null, "k1lib-data-item-label");
                    $value_div = $form_group->append_h6($value, "k1lib-data-item-value");
                    if (!empty($field_alias)) {
                        $form_group->set_id("row-{$field_alias}");
                        $label->set_id("label-{$field_alias}");
                        if (method_exists($value, "set_id")) {
                            $value->set_id("value-{$field_alias}");
                        } else {
                            $value_div->set_id("value-{$field_alias}");
                        }
                    }
                }
            }
            $text_fields_div->append_to($data_group);

            return $this->div_container;
        } else {
            return FALSE;
        }
    }

    /**
     * Gets the CSS classes used for HTML column layout.
     *
     * @return string The HTML column classes.
     */
    public function get_html_column_classes(): string {
        return $this->html_column_classes;
    }

    /**
     * Sets the CSS classes for HTML column layout.
     *
     * @param string $html_column_classes The CSS classes to apply.
     */
    public function set_html_column_classes($html_column_classes): void {
        $this->html_column_classes = $html_column_classes;
    }
}
