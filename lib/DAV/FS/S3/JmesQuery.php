<?php
namespace Afterlogic\DAV\FS\S3;

use Aws\S3\S3Client;

class JmesQuery {
    private $s3Client;
    private $bucket;

    public function __construct(S3Client $s3Client, string $bucket) {
        $this->s3Client = $s3Client;
        $this->bucket = $bucket;
    }

    public static function getInstance(S3Client $s3Client, string $bucket)
    {
        static $oInstance = null;
        if (is_null($oInstance)) {
            $oInstance = new self($s3Client, $bucket);
        }
        return $oInstance;
    }

    /**
     * Query with JMESPath-filter
     * 
     * @param string $path Base path
     * @param array $options {
     *   @type int $limit
     * }
     */
    public function query($path = '', array $options = []): array {
        $path = trim($path, '/') . '/';
        $limit = $options['limit'] ?? 0;

        $params = [
            'Bucket' => $this->bucket,
            'Prefix' => $path,
            'PaginationConfig' => ['PageSize' => 1000],
            'StartAfter' => $path,
            'Delimiter' => '/'
        ];

        $jmesFilters = [
            "starts_with(Key, '{$path}')",
            "Key != '{$path}'" // exclude the folder itself
        ];

        $result = [];

        $jmesQuery = 'Contents[?' . implode(' && ', $jmesFilters) . ']';
        $paginator = $this->s3Client->getPaginator('ListObjectsV2', $params);
        foreach ($paginator->search($jmesQuery) as $item) {            
            $item['IsDir'] = str_ends_with($item['Key'], needle: '/');
            $name = $this->getBaseName($item['Key'] ?? $item['Prefix']);
            if (!$this->shouldExcludeItem($name, $item['IsDir'])) {
                $result[] = $item;
                if ($limit > 0 && count($result) >= $limit) {
                    break;
                }
            }
        }

        // get folders
        if ($limit === 0) {
            foreach ($paginator->search('CommonPrefixes[]') as $dir) {
                if (!$this->shouldExcludeItem($this->getBaseName($dir['Prefix']), true)) {
                    $result[] = [
                        'IsDir' => true,
                        'Key' => $dir['Prefix']
                    ];
                }
            }
        }

        return $result;
    }

    private function getBaseName(string $path): string {
        $path = rtrim($path, '/');
        $parts = explode('/', $path);
        return urldecode(end($parts));
    }

    private function shouldExcludeItem($name, $isDirectory)
    {
        // Exclude .sabredav and folders ending with .hist
        return $name === '.sabredav' || ($isDirectory && str_ends_with($name, '.hist'));
    }
}