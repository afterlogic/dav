<?php

namespace Afterlogic\DAV;

use Sabre\VObject\Splitter\VCard as VCardSplitter;
use Sabre\VObject\Splitter\ICalendar as ICalendarSplitter;

class VObjectSplitter
{
    /**
     * Input data.
     *
     * @var string|resource|null
     */
    protected $input;

    /**
     * Parser options.
     *
     * @var int
     */
    protected $options;

    /**
     * Splitter instance.
     *
     * @var VCardSplitter|ICalendarSplitter|null
     */
    protected $splitter = null;

    /* Constructor.
     *
     * The splitter should receive an readable file stream as its input.
     *
     * @param string $type 'VCard' or 'ICalendar'
     * @param string|resource|null $input
     * @param int                  $options parser options, see the OPTIONS constants
     */ 
    public function __construct($type = 'VCard', $input = null, $options = 0)
    {
        $data = '';
        if (is_string($input)) {
            $data = $this->normalizeVObjectString($input);
        } elseif (is_resource($input)) {
            $data = stream_get_contents($input);
            $data = $this->normalizeVObjectString($data);
        }

        $input = fopen('php://temp', 'r+');
        fwrite($input, $data);
        rewind($input);

        $this->input = $input;
        $this->options = $options;

        if ($type === 'VCard') {
            $this->splitter = new VCardSplitter($this->input, $this->options);
        } elseif ($type === 'ICalendar') {
            $this->splitter = new ICalendarSplitter($this->input, $this->options);
        } else {
            throw new \InvalidArgumentException('Invalid type specified. Use "VCard" or "ICalendar".');
        }
    }

    /**
     * Normalizes a vObject string.
     */
    protected function normalizeVObjectString(string $data): string
    {
        // Remove BOM if present
        $data = preg_replace('/^\xEF\xBB\xBF/', '', $data);

        // Standardize BEGIN and END lines to uppercase and remove extra spaces
        $data = preg_replace_callback(
            '/^\s*(BEGIN|END)\s*[:;]\s*([A-Za-z0-9_-]+)\s*$/mi',
            fn($m) => strtoupper($m[1] . ':' . $m[2]),
            $data
        );

        return $data;
    }

    /**
     * Every time getNext() is called, a new object will be parsed, until we
     * hit the end of the stream.
     *
     * When the end is reached, null will be returned.
     *
     * @return \Sabre\VObject\Component|null
     */
    public function getNext()
    {
        return $this->splitter->getNext();
    }

}
