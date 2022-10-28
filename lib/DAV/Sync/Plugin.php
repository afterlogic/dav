<?php

declare(strict_types=1);

namespace Afterlogic\DAV\Sync;

use Sabre\DAV\Xml\Request\SyncCollectionReport;
use Sabre\HTTP\RequestInterface;

/**
 * This plugin all WebDAV-sync capabilities to the Server.
 *
 * WebDAV-sync is defined by rfc6578
 *
 * The sync capabilities only work with collections that implement
 * Sabre\DAV\Sync\ISyncCollection.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
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
        $syncTokens = null;
        // Pre-fetching children, if this is possible.
        foreach (array_merge($added, $modified) as $item) {
            if (is_array($item)) {
                $syncTokens[$collectionUrl.'/'.$item[0]] = $item[1];
                $item = $item[0];
            }
            $fullPath = $collectionUrl.'/'.$item;
            $fullPaths[] = $fullPath;
        }

        $responses = [];
        try {
            foreach ($this->server->getPropertiesForMultiplePaths($fullPaths, $properties) as $fullPath => $props) {
                // The 'Property_Response' class is responsible for generating a
                // single {DAV:}response xml element.
                if (isset($syncTokens[$fullPath])) {
                    $props[200]['{DAV:}item-sync-token'] = $syncTokens[$fullPath];
                }
                $responses[] = new \Sabre\DAV\Xml\Element\Response($fullPath, $props);
            }
        }
        catch (\Sabre\DAV\Exception\NotFound) {

        }

        // Deleted items also show up as 'responses'. They have no properties,
        // and a single {DAV:}status element set as 'HTTP/1.1 404 Not Found'.
        foreach ($deleted as $item) {
            $props = [];
            if (is_array($item)) {
                $props[200]['{DAV:}item-sync-token'] = $item[1];
                $item = $item[0];
            }
            $fullPath = $collectionUrl.'/'.$item;

            $responses[] = new \Sabre\DAV\Xml\Element\Response($fullPath, $props, 404);
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
