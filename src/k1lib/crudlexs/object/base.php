<?php

namespace k1lib\crudlexs\object;

use \k1lib\db\security\db_table_aliases as db_table_aliases;

/**
 * Base class for all CRUD objects.
 * Provides common functionality for database table operations, authentication,
 * HTML container management, and state tracking.
 */
class base {

    /**
     * Use only key fields when applying HTML tags to filtered data.
     */
    const USE_KEY_FIELDS = 1;

    /**
     * Use all fields when applying HTML tags to filtered data.
     */
    const USE_ALL_FIELDS = 2;

    /**
     * Use label fields when applying HTML tags to filtered data.
     */
    const USE_LABEL_FIELDS = 3;

    /**
     * Global k1magic value shared across all base instances.
     * @var mixed
     */
    static protected $k1magic_value = null;

    /**
     * Database table object associated with this CRUD object.
     * @var \k1lib\crudlexs\db_table
     */
    public $db_table;

    /**
     * Unique identifier for the controller handling this object.
     * @var string
     */
    protected string $controller_id;

    /**
     * HTML div container for the object's content.
     * @var \k1lib\html\div
     */
    protected $div_container;

    /**
     * Unique ID for each object instance.
     * @var string
     */
    protected $object_id = null;

    /**
     * General CSS class for styling the object.
     * @var string
     */
    protected $css_class = null;

    /**
     * Flag to prevent further processing if validation fails.
     * When set to FALSE, subsequent method calls will be skipped.
     * @var bool
     */
    private $is_valid = FALSE;

    /**
     * DOM element ID for notifications and messages.
     * @var string
     */
    protected $notifications_div_id = "k1lib-output";

    /**
     * Retrieves the global k1magic value.
     *
     * @return mixed The k1magic value.
     */
    static function get_k1magic_value(): mixed {
        return self::$k1magic_value;
    }

    /**
     * Sets the global k1magic value.
     *
     * @param mixed $k1magic_value The value to set.
     */
    static function set_k1magic_value($k1magic_value): void {
        self::$k1magic_value = $k1magic_value;
    }

    /**
     * Constructs the base CRUD object.
     * Requires a valid database table object to function properly.
     *
     * @param \k1lib\crudlexs\db_table $db_table Database table object.
     */
    public function __construct(\k1lib\crudlexs\db_table $db_table) {
        $this->db_table = $db_table;
        $this->div_container = new \k1lib\html\div();
        $this->is_valid = TRUE;
    }

    /**
     * Checks if the object is in a valid state.
     *
     * @return bool TRUE if valid, FALSE otherwise.
     */
    function is_valid(): bool {
        return $this->is_valid;
    }

    /**
     * Marks the object as invalid, preventing further processing.
     */
    function make_invalid(): void {
        $this->is_valid = FALSE;
    }

    /**
     * Returns a string representation of the object's state.
     * Always requires a valid database table object to return "1".
     *
     * @return string "1" if state is valid, "0" otherwise.
     */
    public function __toString() {
        if ($this->get_state()) {
            return "1";
        } else {
            return "0";
        }
    }

    /**
     * Determines the overall state of the object based on
     * database table state and validity.
     *
     * @return bool TRUE if both db_table and is_valid are TRUE, FALSE otherwise.
     */
    public function get_state(): bool {
        if (empty($this->db_table) || !$this->is_valid()) {
            return FALSE;
        } else {
            if ($this->db_table->get_state() || !$this->is_valid()) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }

    /**
     * Gets the unique object identifier.
     *
     * @return string The object ID.
     */
    function get_object_id(): ?string {
        return $this->object_id;
    }

    /**
     * Sets the object ID based on the database table name and class name.
     *
     * @param string $class_name The class name to use for ID generation.
     * @return string The generated object ID.
     */
    function set_object_id($class_name): string {
        if (isset($this->db_table) && key_exists($this->db_table->get_db_table_name(), db_table_aliases::$aliases)) {
            $table_name = db_table_aliases::$aliases[$this->db_table->get_db_table_name()];
        } else if (isset($this->db_table)) {
            $table_name = $this->db_table->get_db_table_name();
        } else {
            $table_name = "no-table";
        }
        return $this->object_id = $table_name . "-" . basename(str_replace("\\", "/", $class_name));
    }

    /**
     * Gets the CSS class for this object.
     *
     * @return string The CSS class name.
     */
    function get_css_class(): ?string {
        return $this->css_class;
    }

    /**
     * Sets the CSS class for this object.
     *
     * @param string $class_name The CSS class name to set.
     */
    function set_css_class($class_name): void {
        $this->css_class = basename(str_replace("\\", "/", $class_name));
    }

    /**
     * Gets the notifications div ID.
     *
     * @return string The DOM element ID for notifications.
     */
    public function get_notifications_div_id(): string {
        return $this->notifications_div_id;
    }

    /**
     * Sets the notifications div ID.
     *
     * @param string $notifications_div_id The DOM element ID.
     */
    public function set_notifications_div_id($notifications_div_id): void {
        $this->notifications_div_id = $notifications_div_id;
    }

    /**
     * Sets the controller ID for this object.
     *
     * @param string $controller_id The controller identifier.
     */
    public function set_controller_id(string $controller_id): void {
        $this->controller_id = $controller_id;
    }
}
