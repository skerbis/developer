<?php

/** @var rex_addon $this */

// Get parameters
$itemType = rex_request('item_type', 'string');
$itemId = rex_request('item_id', 'int');
$function = rex_request('function', 'string');
$version = rex_request('version', 'int');
$file = rex_request('file', 'string');

// Check for valid item type
$validTypes = ['templates', 'modules', 'actions', 'yform_email'];
if (!in_array($itemType, $validTypes) || $itemId <= 0) {
    echo rex_view::error($this->i18n('version_invalid_item'));
    return;
}

// Initialize versioning
$versioning = new rex_developer_synchronizer_version($itemType);

// Handle restore action
if ($function === 'restore' && $version > 0 && $file) {
    rex_csrf_token::factory('developer_version_restore')->validate();
    
    $restored = $versioning->restoreVersion($itemId, $version, $file);
    
    if ($restored) {
        // Determine the item type specific update logic
        $success = false;
        
        switch ($itemType) {
            case 'templates':
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('template'));
                $sql->setWhere(['id' => $itemId]);
                $sql->setValue($file, $restored['content']);
                $sql->setValue('updatedate', date('Y-m-d H:i:s'));
                $sql->setValue('updateuser', rex::getUser()->getLogin());
                $success = $sql->update();
                
                // Clear cache if needed
                $template = new rex_template($itemId);
                $template->deleteCache();
                break;
                
            case 'modules':
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('module'));
                $sql->setWhere(['id' => $itemId]);
                $sql->setValue($file, $restored['content']);
                $sql->setValue('updatedate', date('Y-m-d H:i:s'));
                $sql->setValue('updateuser', rex::getUser()->getLogin());
                $success = $sql->update();
                
                // Clear affected article caches
                $sql = rex_sql::factory();
                $sql->setQuery('
                    SELECT     DISTINCT(article.id)
                    FROM       ' . rex::getTable('article') . ' article
                    LEFT JOIN  ' . rex::getTable('article_slice') . ' slice
                    ON         article.id = slice.article_id
                    WHERE      slice.module_id=' . $itemId
                );
                for ($i = 0, $rows = $sql->getRows(); $i < $rows; ++$i) {
                    rex_article_cache::delete($sql->getValue('article.id'));
                    $sql->next();
                }
                break;
                
            case 'actions':
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('action'));
                $sql->setWhere(['id' => $itemId]);
                $sql->setValue($file, $restored['content']);
                $sql->setValue('updatedate', date('Y-m-d H:i:s'));
                $sql->setValue('updateuser', rex::getUser()->getLogin());
                $success = $sql->update();
                break;
                
            case 'yform_email':
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('yform_email_template'));
                $sql->setWhere(['id' => $itemId]);
                $sql->setValue($file, $restored['content']);
                $success = $sql->update();
                break;
        }
        
        if ($success) {
            // Trigger a new synchronization
            rex_developer_manager::synchronize(null, rex_developer_synchronizer::FORCE_DB);
            
            echo rex_view::success($this->i18n('version_restored', $version));
        } else {
            echo rex_view::error($this->i18n('version_restore_error'));
        }
    } else {
        echo rex_view::error($this->i18n('version_restore_error'));
    }
}

// Get item name
$itemName = '';
switch ($itemType) {
    case 'templates':
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT name FROM ' . rex::getTable('template') . ' WHERE id = ' . $itemId);
        if ($sql->getRows() > 0) {
            $itemName = $sql->getValue('name');
        }
        break;
        
    case 'modules':
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT name FROM ' . rex::getTable('module') . ' WHERE id = ' . $itemId);
        if ($sql->getRows() > 0) {
            $itemName = $sql->getValue('name');
        }
        break;
        
    case 'actions':
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT name FROM ' . rex::getTable('action') . ' WHERE id = ' . $itemId);
        if ($sql->getRows() > 0) {
            $itemName = $sql->getValue('name');
        }
        break;
        
    case 'yform_email':
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT name FROM ' . rex::getTable('yform_email_template') . ' WHERE id = ' . $itemId);
        if ($sql->getRows() > 0) {
            $itemName = $sql->getValue('name');
        }
        break;
}

// Get versions
$versions = $versioning->getVersions($itemId);

// Display header with back link
$backUrl = '';
switch ($itemType) {
    case 'templates':
        $backUrl = rex_url::backendPage('templates', ['function' => 'edit', 'template_id' => $itemId]);
        break;
    case 'modules':
        $backUrl = rex_url::backendPage('modules/modules', ['function' => 'edit', 'module_id' => $itemId]);
        break;
    case 'actions':
        $backUrl = rex_url::backendPage('modules/actions', ['function' => 'edit', 'action_id' => $itemId]);
        break;
    case 'yform_email':
        $backUrl = rex_url::backendPage('yform/email/index', ['func' => 'edit', 'template_id' => $itemId]);
        break;
}

$header = '<div class="row">
    <div class="col-sm-8">
        <h1>' . $this->i18n('version_history_for', $itemName) . '</h1>
    </div>
    <div class="col-sm-4 text-right">
        <a href="' . $backUrl . '" class="btn btn-default"><i class="rex-icon rex-icon-back"></i> ' . $this->i18n('back_to_item') . '</a>
    </div>
</div>';

echo $header;

// Display comparison view if requested
if ($function === 'compare' && $version > 0 && $file) {
    $versionData = $versioning->restoreVersion($itemId, $version, $file);
    
    if ($versionData) {
        // Get current content
        $currentContent = '';
        switch ($itemType) {
            case 'templates':
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT ' . $file . ' FROM ' . rex::getTable('template') . ' WHERE id = ' . $itemId);
                if ($sql->getRows() > 0) {
                    $currentContent = $sql->getValue($file);
                }
                break;
                
            case 'modules':
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT ' . $file . ' FROM ' . rex::getTable('module') . ' WHERE id = ' . $itemId);
                if ($sql->getRows() > 0) {
                    $currentContent = $sql->getValue($file);
                }
                break;
                
            case 'actions':
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT ' . $file . ' FROM ' . rex::getTable('action') . ' WHERE id = ' . $itemId);
                if ($sql->getRows() > 0) {
                    $currentContent = $sql->getValue($file);
                }
                break;
                
            case 'yform_email':
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT ' . $file . ' FROM ' . rex::getTable('yform_email_template') . ' WHERE id = ' . $itemId);
                if ($sql->getRows() > 0) {
                    $currentContent = $sql->getValue($file);
                }
                break;
        }
        
        // Prepare file title map
        $fileTitles = [
            'template' => $this->i18n('version_file_template'),
            'content' => $this->i18n('version_file_template'),
            'input' => $this->i18n('version_file_input'),
            'output' => $this->i18n('version_file_output'),
            'preview' => $this->i18n('version_file_preview'),
            'presave' => $this->i18n('version_file_presave'),
            'postsave' => $this->i18n('version_file_postsave'),
            'body' => $this->i18n('version_file_body'),
            'body_html' => $this->i18n('version_file_body_html'),
        ];
        
        $fileTitle = isset($fileTitles[$file]) ? $fileTitles[$file] : $file;
        
        $fragment = new rex_fragment();
        $fragment->setVar('title', $this->i18n('version_comparing', $version, $fileTitle));
        $fragment->setVar('body', '
            <div class="row version-comparison">
                <div class="col-sm-6">
                    <h3>' . $this->i18n('version_current') . '</h3>
                    <pre class="version-content">' . rex_escape($currentContent) . '</pre>
                </div>
                <div class="col-sm-6">
                    <h3>' . $this->i18n('version_old', $version) . ' (' . date('Y-m-d H:i:s', $versionData['metadata']['timestamp']) . ')</h3>
                    <pre class="version-content">' . rex_escape($versionData['content']) . '</pre>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12 text-right">
                    <a href="' . rex_url::currentBackendPage(['function' => 'restore', 'version' => $version, 'file' => $file]) . '&' . rex_csrf_token::factory('developer_version_restore')->getUrlParams() . '" class="btn btn-primary" data-confirm="' . $this->i18n('version_confirm_restore') . '">' . $this->i18n('version_restore') . '</a>
                </div>
            </div>
        ', false);
        
        $content = $fragment->parse('core/page/section.php');
        echo $content;
    }
}

// Display versions list
if (empty($versions)) {
    $fragment = new rex_fragment();
    $fragment->setVar('class', 'info');
    $fragment->setVar('title', $this->i18n('version_information'));
    $fragment->setVar('body', '<p>' . $this->i18n('version_no_history') . '</p>', false);
    $content = $fragment->parse('core/page/section.php');
    echo $content;
} else {
    $fragment = new rex_fragment();
    $fragment->setVar('title', $this->i18n('version_available', count($versions)));
    
    $tableData = [];
    
    foreach ($versions as $v => $metadata) {
        $timestamp = isset($metadata['timestamp']) ? date('Y-m-d H:i:s', $metadata['timestamp']) : '-';
        $user = isset($metadata['user']) ? $metadata['user'] : '-';
        $fileInfo = isset($metadata['file']) ? $metadata['file'] : '-';
        
        // Prepare file title map (same as above)
        $fileTitles = [
            'template' => $this->i18n('version_file_template'),
            'content' => $this->i18n('version_file_template'),
            'input' => $this->i18n('version_file_input'),
            'output' => $this->i18n('version_file_output'),
            'preview' => $this->i18n('version_file_preview'),
            'presave' => $this->i18n('version_file_presave'),
            'postsave' => $this->i18n('version_file_postsave'),
            'body' => $this->i18n('version_file_body'),
            'body_html' => $this->i18n('version_file_body_html'),
        ];
        
        $fileTitle = isset($fileTitles[$fileInfo]) ? $fileTitles[$fileInfo] : $fileInfo;
        
        // Build actions
        $compareUrl = rex_url::currentBackendPage(['function' => 'compare', 'version' => $v, 'file' => $fileInfo]);
        $restoreUrl = rex_url::currentBackendPage(['function' => 'restore', 'version' => $v, 'file' => $fileInfo]) . '&' . rex_csrf_token::factory('developer_version_restore')->getUrlParams();
        
        $actions = '<a href="' . $compareUrl . '" class="btn btn-default btn-xs">' . $this->i18n('version_compare') . '</a> ';
        $actions .= '<a href="' . $restoreUrl . '" class="btn btn-default btn-xs" data-confirm="' . $this->i18n('version_confirm_restore') . '">' . $this->i18n('version_restore') . '</a>';
        
        $tableData[] = [
            'version' => $v,
            'date' => $timestamp,
            'user' => $user,
            'file' => $fileTitle,
            'functions' => $actions
        ];
    }
    
    $tableAttributes = [
        'class' => 'table table-striped table-hover',
    ];
    
    $table = rex_list::factory($tableData, 100000);
    $table->addTableAttribute('class', 'table table-striped table-hover');
    
    $table->setColumnLabel('version', $this->i18n('version_version'));
    $table->setColumnLabel('date', $this->i18n('version_date'));
    $table->setColumnLabel('user', $this->i18n('version_user'));
    $table->setColumnLabel('file', $this->i18n('version_file'));
    $table->setColumnLabel('functions', $this->i18n('version_functions'));
    
    $table->setColumnFormat('functions', 'custom', function ($params) {
        return $params['value'];
    });
    
    $content = $table->get();
    
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');
    echo $content;
}

// Add CSS for comparison view
?>
<style>
.version-comparison .version-content {
    height: 500px;
    overflow: auto;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.5;
    background-color: #f9f9f9;
    border: 1px solid #e1e1e1;
    border-radius: 3px;
    padding: 10px;
}
</style>
