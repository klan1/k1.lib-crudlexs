<?php

namespace k1lib\crudlexs\object;

use k1lib\common_strings;

/**
 * Helper class for rendering read-only field values.
 * Provides static methods for formatting different field types for display.
 */
class read_helper
{
    /**
     * Formats a password field value for display.
     * Returns the value as-is (passwords are typically masked elsewhere).
     *
     * @param mixed $value The password value.
     * @return mixed The formatted value.
     */
    static function password_type($value): mixed
    {
        return $value;
    }

    /**
     * Formats an enum field value for display.
     *
     * @param mixed $value The enum value.
     * @return mixed The formatted value.
     */
    static function enum_type($value): mixed
    {
        return $value;
    }

    /**
     * Formats a text field value for display.
     *
     * @param mixed $value The text value.
     * @return mixed The formatted value.
     */
    static function text_type($value): mixed
    {
        return $value;
    }

    /**
     * Formats a file upload field value for display.
     *
     * @param mixed $value The file upload value.
     * @return mixed The formatted value.
     */
    static function file_upload($value): mixed
    {
        return $value;
    }

    /**
     * Formats a boolean field value for display.
     * Converts internal boolean representation to localized Yes/No text.
     *
     * @param mixed $value The boolean value.
     * @return string Localized "Yes" or "No" string.
     */
    static function boolean_type($value): string
    {
        $t = \k1lib\lang\translator::getInstance();
        if (self::$boolean_true === NULL) {
            self::$boolean_true = $t->t('k1lib', '', 'Yes');
        }
        if (self::$boolean_false === NULL) {
            self::$boolean_false = $t->t('k1lib', '', 'No');
        }
        return $value;
    }

    /**
     * Formats a default field value for display.
     *
     * @param mixed $value The field value.
     * @return mixed The formatted value.
     */
    static function default_type($value): mixed
    {
        return $value;
    }
}
