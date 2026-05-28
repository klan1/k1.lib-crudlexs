<?php

namespace k1lib\crudlexs\object;

use k1lib\crudlexs\db_table;
use k1lib\html\div;
use k1lib\html\input;
use function k1lib\common\unserialize_var;

/**
 * Search helper object for creating search forms.
 * Extends creating functionality to provide search interface with caller communication.
 */
class search_helper extends creating {

    /**
     * Database table data for search criteria.
     * @var array
     */
    public $db_table_data = FALSE;

    /**
     * Keys for the database table data.
     * @var bool
     */
    protected $db_table_data_keys = FALSE;

    /**
     * ID of the calling object for result communication.
     * @var string
     */
    protected $caller_objetc_id = null;

    /**
     * Whether to enable POST data capture for search.
     * @var bool
     */
    protected $search_catch_post_enable = TRUE;

    /**
     * URL of the calling object for result communication.
     * @var string
     */
    protected $caller_url = null;

    /**
     * Constructs a search helper object.
     *
     * @param db_table $db_table The database table object.
     */
    public function __construct(db_table $db_table) {
        parent::__construct($db_table, FALSE);
        if (isset($_GET['caller-id'])) {
            $this->caller_url = urldecode($_GET['caller-id']);
        } else {
            d("No caller ID");
        }
        creating_strings::$button_submit = search_helper_strings::$button_submit;
        creating_strings::$button_cancel = search_helper_strings::$button_cancel;

        $this->show_cancel_button = FALSE;

        $this->set_do_table_field_name_encrypt(TRUE);

        $this->db_table->set_db_table_show_rule("show-search");
    }

    /**
     * Generates and returns the HTML search form.
     * Overrides parent to wrap form in container and set form attributes.
     *
     * @return div The search form container.
     */
    public function do_html_object(): \k1lib\html\div {
        if ($this->search_catch_post_enable && $this->catch_post_data()) {
            $this->put_post_data_on_table_data();
            $this->db_table->set_query_filter($this->post_incoming_array, FALSE);
        }
        $this->apply_label_filter();

        $this->insert_inputs_on_data_row();

        $div_container = new div('container');

        $search_html = parent::do_html_object();
        $search_html->get_elements_by_tag("form")[0]->set_attrib("action", unserialize_var($this->caller_url . '-url'));
        $search_html->get_elements_by_tag("form")[0]->set_attrib("target", "_parent");
        $search_html->get_elements_by_tag("form")[0]->append_child(new input("hidden", "from-search", urlencode($this->caller_url)));

        $search_html->append_to($div_container);
        return $div_container;
    }

    /**
     * Captures and processes POST data from search form.
     * Overrides parent to merge with caller's serialized search data.
     *
     * @return bool TRUE if data captured successfully, FALSE otherwise.
     */
    function catch_post_data(): bool {
        $search_post = unserialize_var(urlencode($this->caller_url));
        if (empty($search_post)) {
            $search_post = [];
        }
        $_POST = array_merge($search_post, $_POST);
        if (parent::catch_post_data()) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Sets whether to enable POST data capture for search.
     *
     * @param bool $search_catch_post_enable Whether to enable search POST capture.
     * @return void
     */
    public function set_search_catch_post_enable($search_catch_post_enable): void {
        $this->search_catch_post_enable = $search_catch_post_enable;
    }
}
