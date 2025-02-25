<?php

/**
 * Class for managing file versions
 *
 * @author Developer AddOn
 */
class rex_developer_synchronizer_version
{
    const VERSION_DIR = 'versions';
    const MAX_VERSIONS = 10; // Default number of versions to keep

    private $basePath;
    private $dirname;
    private $maxVersions;
    
    /**
     * Constructor
     *
     * @param string $dirname Name of directory to manage versions for
     * @param int $maxVersions Maximum number of versions to keep (0 = unlimited)
     */
    public function __construct($dirname, $maxVersions = 0)
    {
        $this->dirname = $dirname;
        $this->basePath = rex_developer_manager::getBasePath() . '/' . $dirname . '/';
        $this->maxVersions = $maxVersions > 0 ? $maxVersions : (int) rex_config::get('developer', 'max_versions', self::MAX_VERSIONS);
    }
    
    /**
     * Creates a version backup of a file
     *
     * @param rex_developer_synchronizer_item $item The item being modified
     * @param string $file The filename within the item
     * @param string $content The previous content of the file
     * @return boolean Success
     */
    public function createVersion(rex_developer_synchronizer_item $item, $file, $content)
    {
        // Skip empty files
        if (empty($content)) {
            return false;
        }
        
        $id = $item->getId();
        $versionDir = $this->getVersionDir($id);
        
        // Create version directory if it doesn't exist
        if (!is_dir($versionDir)) {
            if (!rex_dir::create($versionDir)) {
                return false;
            }
        }
        
        // Generate version metadata
        $timestamp = time();
        $user = rex::getUser() ? rex::getUser()->getLogin() : 'system';
        
        $metadata = [
            'version' => $this->getNextVersionNumber($id),
            'timestamp' => $timestamp,
            'user' => $user,
            'file' => $file,
            'item_id' => $id,
            'item_name' => $item->getName()
        ];
        
        // Store the content and metadata
        $versionFile = $this->getVersionFilename($id, $metadata['version'], $file);
        $metadataFile = $versionFile . '.yml';
        
        if (!rex_file::put($versionFile, $content)) {
            return false;
        }
        
        if (!rex_file::put($metadataFile, rex_string::yamlEncode($metadata))) {
            // Clean up if metadata couldn't be saved
            rex_file::delete($versionFile);
            return false;
        }
        
        // Clean up old versions if needed
        $this->cleanupVersions($id);
        
        return true;
    }
    
    /**
     * Restores a specific version of a file
     *
     * @param int $id Item ID
     * @param int $version Version number
     * @param string $file Filename to restore
     * @return array|false The restored content or false on failure
     */
    public function restoreVersion($id, $version, $file)
    {
        $versionFile = $this->getVersionFilename($id, $version, $file);
        $metadataFile = $versionFile . '.yml';
        
        if (!file_exists($versionFile) || !file_exists($metadataFile)) {
            return false;
        }
        
        $content = rex_file::get($versionFile);
        $metadata = rex_string::yamlDecode(rex_file::get($metadataFile));
        
        if ($content === false || $metadata === false) {
            return false;
        }
        
        return [
            'content' => $content,
            'metadata' => $metadata
        ];
    }
    
    /**
     * Gets a list of all versions for an item
     *
     * @param int $id Item ID
     * @return array Array of version metadata
     */
    public function getVersions($id)
    {
        $versionDir = $this->getVersionDir($id);
        
        if (!is_dir($versionDir)) {
            return [];
        }
        
        $versions = [];
        $metadataFiles = glob($versionDir . '/*.yml');
        
        if (is_array($metadataFiles)) {
            foreach ($metadataFiles as $file) {
                $metadata = rex_string::yamlDecode(rex_file::get($file));
                if ($metadata && isset($metadata['version'])) {
                    $versions[$metadata['version']] = $metadata;
                }
            }
        }
        
        // Sort by version number (desc)
        krsort($versions);
        
        return $versions;
    }
    
    /**
     * Get the next version number for an item
     *
     * @param int $id Item ID
     * @return int Next version number
     */
    private function getNextVersionNumber($id)
    {
        $versions = $this->getVersions($id);
        
        if (empty($versions)) {
            return 1;
        }
        
        // Get the highest version number
        return max(array_keys($versions)) + 1;
    }
    
    /**
     * Remove old versions if the limit is reached
     *
     * @param int $id Item ID
     * @return void
     */
    private function cleanupVersions($id)
    {
        if ($this->maxVersions <= 0) {
            return; // No limit set
        }
        
        $versions = $this->getVersions($id);
        
        if (count($versions) <= $this->maxVersions) {
            return; // Not enough versions to clean up
        }
        
        // Sort by version number (asc)
        ksort($versions);
        
        // Calculate how many versions to remove
        $removeCount = count($versions) - $this->maxVersions;
        $versionsToRemove = array_slice($versions, 0, $removeCount, true);
        
        foreach ($versionsToRemove as $version => $metadata) {
            if (isset($metadata['file'])) {
                $versionFile = $this->getVersionFilename($id, $version, $metadata['file']);
                $metadataFile = $versionFile . '.yml';
                
                rex_file::delete($versionFile);
                rex_file::delete($metadataFile);
            }
        }
    }
    
    /**
     * Get the path to the version directory for an item
     *
     * @param int $id Item ID
     * @return string Path to version directory
     */
    private function getVersionDir($id)
    {
        return rex_path::addonData('developer', self::VERSION_DIR . '/' . $this->dirname . '/' . $id);
    }
    
    /**
     * Get the filename for a specific version file
     *
     * @param int $id Item ID
     * @param int $version Version number
     * @param string $file Original filename
     * @return string Path to version file
     */
    private function getVersionFilename($id, $version, $file)
    {
        return $this->getVersionDir($id) . '/' . $version . '.' . $file;
    }
}
