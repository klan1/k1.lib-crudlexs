<?php

namespace k1lib\crudlexs\object;

/**
 * Base interface for all CRUD objects.
 * Defines the contract that all object types must implement to render HTML content.
 */
interface base_interface {

    /**
     * Renders and returns the HTML representation of the CRUD object.
     *
     * @return mixed The HTML object representation, or FALSE if rendering fails.
     */
    public function do_html_object();
}
