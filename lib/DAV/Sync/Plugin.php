<?php

namespace Afterlogic\DAV\Sync;

class Plugin extends \Sabre\DAV\Sync\Plugin
{
    /**
     * Sends the response to a sync-collection request.
     *
     * @param string $syncToken
     * @param string $collectionUrl
     */
    protected function sendSyncCollectionResponse($syncToken, $collectionUrl, array $added, array $modified, array $deleted, array $properties, bool $resultTruncated = false)
    {
        $fullPaths = [];

        $renamed = [];
        // Pre-fetching children, if this is possible.
        foreach (array_merge($added, $modified) as $item) {
            if (is_array($item)) {
                $renamed[$collectionUrl.'/'.$item[0]] = $item[1];
                $item = $item[0];
            } else {
                $fullPath = $collectionUrl.'/'.$item;
                $fullPaths[] = $fullPath;
            }
        }

        $responses = [];
        foreach ($this->server->getPropertiesForMultiplePaths($fullPaths, $properties) as $fullPath => $props) {
            // The 'Property_Response' class is responsible for generating a
            // single {DAV:}response xml element.
            if (isset($renamed[$fullPath])) {
                $props[200]['{DAV:}newuri'] = $renamed[$fullPath];
            }
            $responses[] = new \Sabre\DAV\Xml\Element\Response($fullPath, $props);
        }

        foreach ($renamed as $key => $item) {
            $responses[] = new \Sabre\DAV\Xml\Element\Response($key, [200 => ['{DAV:}newuri' => $item]]);
        }

        // Deleted items also show up as 'responses'. They have no properties,
        // and a single {DAV:}status element set as 'HTTP/1.1 404 Not Found'.
        foreach ($deleted as $item) {
            $fullPath = $collectionUrl.'/'.$item;
            $responses[] = new \Sabre\DAV\Xml\Element\Response($fullPath, [], 404);
        }
        if ($resultTruncated) {
            $responses[] = new \Sabre\DAV\Xml\Element\Response($collectionUrl.'/', [], 507);
        }

        $multiStatus = new \Sabre\DAV\Xml\Response\MultiStatus($responses, self::SYNCTOKEN_PREFIX.$syncToken);

        $this->server->httpResponse->setStatus(207);
        $this->server->httpResponse->setHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->server->httpResponse->setBody(
            $this->server->xml->write('{DAV:}multistatus', $multiStatus, $this->server->getBaseUri())
        );
    }
}