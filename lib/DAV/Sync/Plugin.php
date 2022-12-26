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
    protected function getPropertiesForMultiplePaths($fullPaths, $properties)
    {
        $result = [];
        foreach ($fullPaths as $path) {
            $node = $this->server->getNodeForPath($path);
            if ($node) {
                $propFind = new \Sabre\DAV\PropFind($path, $properties);
                $r = $this->server->getPropertiesByNode($propFind, $node);
                if ($r) {
                    $propFind->
                    $result[$path] = $propFind->getResultForMultiStatus();
                    $result[$path]['href'] = $path;

                    $resourceType = $this->server->getResourceTypeForNode($node);
                    if (in_array('{DAV:}collection', $resourceType) || in_array('{DAV:}principal', $resourceType)) {
                        $result[$path]['href'] .= '/';
                    }
                }
            }
        }

        return $result;
    }
    
    /**
     * Sends the response to a sync-collection request.
     *
     * @param string $syncToken
     * @param string $collectionUrl
     */
    protected function sendSyncCollectionResponse($syncToken, $collectionUrl, array $added, array $modified, array $deleted, array $properties, bool $resultTruncated = false)
    {
        $fullPaths = [];
        // Pre-fetching children, if this is possible.
        // Pre-fetching children, if this is possible.
        foreach (array_merge($added, $modified) as $item) {
            $fullPath = $collectionUrl.'/'.$item[0];
            $fullPaths[] = $fullPath;
        }

        $responses = [];

        foreach ($this->getPropertiesForMultiplePaths($fullPaths, $properties) as $fullPath => $props) {
            // The 'Property_Response' class is responsible for generating a
            // single {DAV:}response xml element.
            $responses[] = new \Sabre\DAV\Xml\Element\Response($fullPath, $props);
        }

        // Deleted items also show up as 'responses'. They have no properties,
        // and a single {DAV:}status element set as 'HTTP/1.1 404 Not Found'.
        foreach ($deleted as $item) {
            $fullPath = $collectionUrl.'/'.$item[0];
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
