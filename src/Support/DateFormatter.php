<?php

declare(strict_types=1);

namespace Wicket\Finance\Support;

/**
 * Date formatting and validation service.
 *
 * Handles date validation, formatting, and conversion between storage and display formats.
 * - Storage format: Y-m-d (2024-01-15)
 * - Display format: Site locale/timezone via date_i18n
 *
 * @since 1.0.0
 */
class DateFormatter
{
    /**
     * Storage date format.
     */
    private const STORAGE_FORMAT = 'Y-m-d';

    /**
     * Validates a date string.
     *
     * @param string $date   Date string to validate.
     * @param string $format Expected format (default: Y-m-d).
     * @return bool True if valid, false otherwise.
     */
    public function validate(string $date, string $format = self::STORAGE_FORMAT): bool
    {
        if (empty($date)) {
            return false;
        }

        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }

    /**
     * Validates that end date is greater than or equal to start date.
     *
     * @param string $start_date Start date in Y-m-d format.
     * @param string $end_date   End date in Y-m-d format.
     * @return bool True if valid (end >= start), false otherwise.
     */
    public function validate_date_range(string $start_date, string $end_date): bool
    {
        if (!$this->validate($start_date) || !$this->validate($end_date)) {
            return false;
        }

        return strtotime($end_date) >= strtotime($start_date);
    }

    /**
     * Formats a date for display using site locale and timezone.
     *
     * @param string $date Date in Y-m-d storage format.
     * @return string Localized date string, or empty string if invalid.
     */
    public function format_for_display(string $date): string
    {
        if (!$this->validate($date)) {
            return '';
        }

        // Use WordPress date_i18n with site date format
        return date_i18n(get_option('date_format'), strtotime($date));
    }

    /**
     * Converts a date to storage format (Y-m-d).
     *
     * @param string $date   Date string in various formats.
     * @param string $format Input format (optional).
     * @return string Date in Y-m-d format, or empty string if invalid.
     */
    public function to_storage_format(string $date, string $format = ''): string
    {
        if (empty($date)) {
            return '';
        }

        // If already in storage format, validate and return
        if ($this->validate($date, self::STORAGE_FORMAT)) {
            return $date;
        }

        // Try to parse with provided format
        if (!empty($format)) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d) {
                return $d->format(self::STORAGE_FORMAT);
            }
        }

        // Try strtotime as fallback
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return gmdate(self::STORAGE_FORMAT, $timestamp);
        }

        return '';
    }

    /**
     * Sanitizes and validates a date input from user.
     *
     * Returns empty string if invalid.
     *
     * @param mixed $date Date value to sanitize.
     * @return string Sanitized date in Y-m-d format, or empty string.
     */
    public function sanitize_date_input($date): string
    {
        if (empty($date) || !is_string($date)) {
            return '';
        }

        $date = sanitize_text_field($date);

        return $this->to_storage_format($date);
    }

    /**
     * Gets the current date in storage format.
     *
     * @return string Current date in Y-m-d format.
     */
    public function get_current_date(): string
    {
        return gmdate(self::STORAGE_FORMAT);
    }

    /**
     * Converts ISO 8601 format (from membership plugin) to storage format.
     *
     * The membership plugin returns dates like: 2024-01-15T00:00:00+00:00
     * We need to extract just the Y-m-d portion.
     *
     * @param string $iso_date Date in ISO 8601 format.
     * @return string Date in Y-m-d format, or empty string if invalid.
     */
    public function from_iso_8601(string $iso_date): string
    {
        if (empty($iso_date)) {
            return '';
        }

        // Extract Y-m-d from ISO 8601
        $date_part = substr($iso_date, 0, 10);

        if ($this->validate($date_part, self::STORAGE_FORMAT)) {
            return $date_part;
        }

        return '';
    }

    /**
     * Gets storage format string.
     *
     * @return string Storage format (Y-m-d).
     */
    public function get_storage_format(): string
    {
        return self::STORAGE_FORMAT;
    }
}
