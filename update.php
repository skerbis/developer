<?php

/**
 * @var rex_addon $this
 */

if (rex_string::versionCompare($this->getVersion(), '3.4.1', '<')) {
    rex_file::delete($this->getDataPath('actions/.rex_id_list'));
    rex_file::delete($this->getDataPath('modules/.rex_id_list'));
    rex_file::delete($this->getDataPath('templates/.rex_id_list'));
}

if (rex_string::versionCompare($this->getVersion(), '3.5.0', '<')) {
    $this->setConfig('sync_frontend', true);
}

if (rex_string::versionCompare($this->getVersion(), '3.6.0', '<')) {
    $this->setConfig('sync_backend', true);
}

if (rex_string::versionCompare($this->getVersion(), '3.6.0-beta2', '<')) {
    $this->setConfig('dir_suffix', false);
}

if (rex_string::versionCompare($this->getVersion(), '3.10.0', '<')) {
    if (!$this->hasConfig('versioning')) {
        $this->setConfig('versioning', true);
    }
    if (!$this->hasConfig('max_versions')) {
        $this->setConfig('max_versions', 10);
    }
    
    // Create the versions directory if it doesn't exist
    $versionDir = rex_path::addonData('developer', rex_developer_synchronizer_version::VERSION_DIR);
    if (!rex_dir::create($versionDir)) {
        rex_logger::logError(
            E_WARNING,
            'Failed to create versions directory: ' . $versionDir,
            __FILE__,
            __LINE__
        );
    }
}
