<?php

/**
 *  @var rex_addon $this
 */

if (method_exists('rex', 'getConsole') && rex::getConsole()) {
    return;
}

if (rex_addon::get('media_manager')->isAvailable() && rex_media_manager::getMediaType() && rex_media_manager::getMediaFile()) {
    return;
}

if (
    !rex::isBackend() && $this->getConfig('sync_frontend') ||
    rex::getUser() && rex::isBackend() && $this->getConfig('sync_backend')
) {
    rex_extension::register('PACKAGES_INCLUDED', function () {
        if (rex::isDebugMode() || ($user = rex_backend_login::createUser()) && $user->isAdmin()) {
            rex_developer_manager::start();
        }
    });
}

rex_extension::register('EDITOR_URL', function (rex_extension_point $ep) {
    if (!preg_match('@^rex:///(template|module|action)/(\d+)(?:/([^/]+))?@', $ep->getParam('file'), $match)) {
        return null;
    }

    $type = $match[1];
    $id = $match[2];

    if (!$this->getConfig($type.'s')) {
        return null;
    }

    if ('template' === $type) {
        $subtype = 'template';
    } elseif (!isset($match[3])) {
        return null;
    } else {
        $subtype = $match[3];
    }

    $path = rtrim(rex_developer_manager::getBasePath(), '/\\').'/'.$type.'s';

    if (!$files = rex_developer_synchronizer::glob("$path/*/$id.rex_id", GLOB_NOSORT)) {
        return null;
    }

    $path = dirname($files[0]);

    if (!$files = rex_developer_synchronizer::glob("$path/*$subtype.php", GLOB_NOSORT)) {
        return null;
    }

    return rex_editor::factory()->getUrl($files[0], $ep->getParam('line'));
}, rex_extension::LATE);



<?php

/**
 * This file contains code that should be added to boot.php to integrate
 * version history links into the REDAXO backend pages
 */

// Add this to the boot.php file
if (rex::isBackend() && rex::getUser() && rex::getUser()->isAdmin() && $this->getConfig('versioning')) {
    // Add version history buttons to templates, modules and actions pages
    rex_extension::register('PAGE_HEADER', function (rex_extension_point $ep) {
        $subject = $ep->getSubject();
        
        // Add CSS for version history button
        $subject .= '
        <style>
        .developer-version-history-btn {
            margin-left: 5px;
        }
        </style>';
        
        return $subject;
    });

    // For Templates
    if ($this->getConfig('templates')) {
        rex_extension::register('STRUCTURE_CONTENT_TEMPLATE_EDIT', function (rex_extension_point $ep) {
            $params = $ep->getParams();
            $templateId = $params['template_id'] ?? $params['id'] ?? 0;
            
            if ($templateId > 0) {
                $btn = ' <a href="' . rex_url::backendPage('developer/versions', [
                    'item_type' => 'templates',
                    'item_id' => $templateId
                ]) . '" class="btn btn-default developer-version-history-btn"><i class="fa fa-history"></i> ' . $this->i18n('version_history') . '</a>';
                
                $subject = $ep->getSubject();
                $subject = str_replace('</h1>', '</h1>' . $btn, $subject);
                $ep->setSubject($subject);
            }
            
            return $ep->getSubject();
        });
    }

    // For Modules
    if ($this->getConfig('modules')) {
        rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) {
            $params = [];
            $moduleId = rex_request('module_id', 'int', 0);
            $page = rex_be_controller::getCurrentPage();
            
            if ($moduleId > 0 && $page == 'modules/modules') {
                $btn = '<a href="' . rex_url::backendPage('developer/versions', [
                    'item_type' => 'modules',
                    'item_id' => $moduleId
                ]) . '" class="btn btn-default developer-version-history-btn"><i class="fa fa-history"></i> ' . $this->i18n('version_history') . '</a>';
                
                $subject = $ep->getSubject();
                
                // Try to insert the button after the heading
                $pattern = '/<h1[^>]*>(.*?)<\/h1>/is';
                $replacement = '<h1$1</h1>' . $btn;
                $newSubject = preg_replace($pattern, $replacement, $subject);
                
                if ($newSubject != $subject) {
                    $ep->setSubject($newSubject);
                }
            }
            
            return $ep->getSubject();
        });
    }

    // For Actions
    if ($this->getConfig('actions')) {
        rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) {
            $params = [];
            $actionId = rex_request('action_id', 'int', 0);
            $page = rex_be_controller::getCurrentPage();
            
            if ($actionId > 0 && $page == 'modules/actions') {
                $btn = '<a href="' . rex_url::backendPage('developer/versions', [
                    'item_type' => 'actions',
                    'item_id' => $actionId
                ]) . '" class="btn btn-default developer-version-history-btn"><i class="fa fa-history"></i> ' . $this->i18n('version_history') . '</a>';
                
                $subject = $ep->getSubject();
                
                // Try to insert the button after the heading
                $pattern = '/<h1[^>]*>(.*?)<\/h1>/is';
                $replacement = '<h1$1</h1>' . $btn;
                $newSubject = preg_replace($pattern, $replacement, $subject);
                
                if ($newSubject != $subject) {
                    $ep->setSubject($newSubject);
                }
            }
            
            return $ep->getSubject();
        });
    }

    // For YForm Email Templates
    if ($this->getConfig('yform_email') && rex_plugin::get('yform', 'email')->isAvailable()) {
        rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) {
            $params = [];
            $templateId = rex_request('template_id', 'int', 0);
            $page = rex_be_controller::getCurrentPage();
            
            if ($templateId > 0 && $page == 'yform/email/index') {
                $btn = '<a href="' . rex_url::backendPage('developer/versions', [
                    'item_type' => 'yform_email',
                    'item_id' => $templateId
                ]) . '" class="btn btn-default developer-version-history-btn"><i class="fa fa-history"></i> ' . $this->i18n('version_history') . '</a>';
                
                $subject = $ep->getSubject();
                
                // Try to insert the button after the heading
                $pattern = '/<h1[^>]*>(.*?)<\/h1>/is';
                $replacement = '<h1$1</h1>' . $btn;
                $newSubject = preg_replace($pattern, $replacement, $subject);
                
                if ($newSubject != $subject) {
                    $ep->setSubject($newSubject);
                }
            }
            
            return $ep->getSubject();
        });
    }

    // Add version history button to file editor if opened via developer AddOn paths
    rex_extension::register('OUTPUT_FILTER', function (rex_extension_point $ep) {
        $page = rex_be_controller::getCurrentPage();
        
        if ($page == 'system/log/redaxo' && rex_get('log_file', 'string', '') == '') {
            $subject = $ep->getSubject();
            
            // Get the edited file path
            $filePath = rex_get('file', 'string', '');
            
            if ($filePath) {
                // Check if the file is in the developer data path
                $developerPath = rex_path::addonData('developer');
                
                if (strpos($filePath, $developerPath) === 0) {
                    // Extract the item type and ID from the path
                    $relativePath = substr($filePath, strlen($developerPath) + 1);
                    $pathParts = explode('/', $relativePath);
                    
                    if (count($pathParts) >= 2) {
                        $itemType = $pathParts[0];
                        
                        // Find the item ID
                        $idFile = null;
                        $fileDir = dirname($filePath);
                        $files = glob($fileDir . '/*.rex_id');
                        
                        if (!empty($files)) {
                            $idFile = $files[0];
                            $itemId = (int) basename($idFile, '.rex_id');
                            
                            if ($itemId > 0) {
                                $btn = '<a href="' . rex_url::backendPage('developer/versions', [
                                    'item_type' => $itemType,
                                    'item_id' => $itemId
                                ]) . '" class="btn btn-default developer-version-history-btn"><i class="fa fa-history"></i> ' . $this->i18n('version_history') . '</a>';
                                
                                // Try to insert the button after the heading
                                $pattern = '/<h1[^>]*>(.*?)<\/h1>/is';
                                $replacement = '<h1$1</h1>' . $btn;
                                $newSubject = preg_replace($pattern, $replacement, $subject);
                                
                                if ($newSubject != $subject) {
                                    $ep->setSubject($newSubject);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $ep->getSubject();
    });
}
