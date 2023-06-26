<?php
/**
 * History log search and report generation form.
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * History log search and report generation form class.
 *
 * @package HistoryLog
 */
class HistoryLog_Form_Search extends Omeka_Form
{
    /**
     * Construct the report generation form.
     */
    public function init()
    {
        parent::init();

        try {
            $recordTypeOptions = $this->_getRecordTypeOptions();
            $collectionOptions = $this->_getCollectionOptions();
            $userOptions = $this->_getUserOptions();
            $operationOptions = $this->_getoperationOptions();
            $elementOptions = $this->_getElementOptions();
            $exportOptions = $this->_getexportOptions();
        } catch (Exception $e) {
            throw $e;
        }

        // Record type.
        $this->addElement('select', 'record_type', array(
            'label' => __('Record Type'),
            'description' => __("The type of record whose log information will be retrieved."),
            'value' => '',
            'order' => 1,
            'validators' => array(
                'alnum',
            ),
            'required' => false,
            'multiOptions' => $recordTypeOptions,
        ));

        // Collection.
        $this->addElement('select', 'collection', array(
            'label' => __('Collection'),
            'description' => __("If record type is %sItem%s, the collection whose items' log information will be retrieved.", '<strong>', '</strong>'),
            'value' => '',
            'order' => 2,
            'validators' => array(
                'digits',
            ),
            'required' => false,
            'multiOptions' => $collectionOptions,
        ));

        // Item.
        $this->addElement('text', 'item', array(
            'label' => __('Item'),
            'description' => __("If record type is %sFile%s, the item or range of items whose files' log information will be retrieved.", '<strong>', '</strong>'),
            'value' => '',
            'order' => 3,
            'validators' => array(
                'digits',
            ),
            'required' => false,
        ));

        // User(s).
        $this->addElement('select', 'user', array(
            'label' => __('User(s)'),
            'description' => __('All administrator users whose edits will be retrieved.'),
            'value' => '',
            'order' => 4,
            'validators' => array(
                'digits',
            ),
            'required' => false,
            'multiOptions' => $userOptions,
        ));

        // Operations.
        $this->addElement('select', 'operation', array(
            'label' => __('Operation'),
            'description' => __('Logged curatorial operations to retrieve in this report.'),
            'value' => '',
            'order' => 5,
            'validators' => array(
                'alnum',
            ),
            'required' => false,
            'multiOptions' => $operationOptions,
        ));

        // Elements.
        $this->addElement('select', 'element', array(
            'label' => __('Element'),
            'description' => __('Limit response with the selected element.')
                . ' ' . __('This field is only available for events %1$sCreate%2$s and %1$sUpdate%2$s.', '<strong>', '</strong>'),
            'value' => '',
            'order' => 6,
            'validators' => array(
                'digits',
            ),
            'required' => false,
            'multiOptions' => $elementOptions,
        ));

        // Date since.
        $this->addElement('text', 'since', array(
            'label' => __('Start Date'),
            'description' => __('The earliest date from which to retrieve logs.'),
            'value' => 'YYYY-MM-DD',
            'order' => 7,
            'style' => 'max-width: 120px;',
            'required' => false,
            'validators' => array(
                array(
                    'Date',
                    false,
                    array(
                        'format' => 'yyyy-mm-dd',
                    )
                )
            )
        ));

        // Date until.
        $this->addElement('text', 'until', array(
            'label' => __('End Date'),
            'description' => __('The latest date, included, from which to retrieve logs.'),
            'value' => 'YYYY-MM-DD',
            'order' => 8,
            'style' => 'max-width: 120px;',
            'required' => false,
            'validators' => array(
                array(
                    'Date',
                    false,
                    array(
                        'format' => 'yyyy-mm-dd',
                    )
                )
            )
        ));

        // Output.
        $this->addElement('radio', 'export', array(
            'label' => __('Output'),
            'value' => '',
            'order' => 9,
            'validators' => array(
                'alnum',
            ),
            'required' => false,
            'multiOptions' => $exportOptions,
        ));

        $this->addElement('checkbox', 'export-headers', array(
            'label' => __('Include headers'),
            'value' => true,
            'order' => 10,
            'required' => false,
        ));

        if (version_compare(OMEKA_VERSION, '2.2.1') >= 0) {
            $this->addElement('hash', 'history_log_token');
        }

        // Button for submit.
        $this->addElement('submit', 'submit-search', array(
            'label' => __('Report'),
        ));

        // TODO Add decorator as in "items/search-form.php" for scroll.

        // Display Groups.
        $this->addDisplayGroup(array(
            'record_type',
            'collection',
            'item',
            'user',
            'operation',
            'element',
            'since',
            'until',
            'export',
            'export-headers',
        ), 'fields');

        $this->addDisplayGroup(
            array(
                'submit-search'
            ),
            'submit_buttons'
        );
    }

    /**
     * Retrieve possible record types as selectable option list.
     *
     * @return array $options An associative array of the logged record event
     * types.
     */
    protected function _getRecordTypeOptions()
    {
        return array(
            '' => __('All types of record'),
            'Item' => __('Items'),
            'Collection' => __('Collections'),
            'File' => __('Files'),
        );
    }

    /**
     * Retrieve Collections as selectable option list.
     *
     * @return array $collections An associative array of the collection IDs and
     * titles.
     */
    protected function _getCollectionOptions()
    {
        return get_table_options('Collection', __('All Collections'));
    }

    /**
     * Retrieve Omeka Admin Users as selectable option list
     *
     * @return array $users  An associative array of the user ids and usernames
     * of all omeka users with admin privileges.
     */
    protected function _getUserOptions()
    {
        $options = array(
            '' => __('All Users'),
            '0' => __('Anonymous User'),
        );

        try {
            $acl = get_acl();
            $roles = $acl->getRoles();
            foreach ($roles as $role) {
                $users = get_records('User', array(
                        'role' => $role,
                    ), '0');
                foreach ($users as $user) {
                    $options[$user->id] = $user->name . ' (' . $role . ')';
                }
            }
        } catch (Exception $e) {
            throw ($e);
        }

        return $options;
    }

    /**
     * Retrieve possible log operations as selectable option list.
     *
     * @return array $options An associative array of the operations.
     */
    protected function _getOperationOptions()
    {
        return array(
            '' => __('All Actions'),
            HistoryLogEntry::OPERATION_CREATE => __('Record Created'),
            HistoryLogEntry::OPERATION_UPDATE => __('Record Updated'),
            HistoryLogEntry::OPERATION_DELETE => __('Record Deleted'),
            HistoryLogEntry::OPERATION_IMPORT => __('Record Imported'),
            HistoryLogEntry::OPERATION_EXPORT => __('Record Exported'),
        );
    }

    /**
     * Retrieve possible elements as a selectable option list.
     *
     * @todo Add deleted elements that are used in old entries.
     *
     * @return array $options An associative array of the elements.
     */
    protected function _getElementOptions()
    {
        return get_table_options(
            'Element',
            null,
            array(
            'record_types' => array('Item', 'All'),
            'sort' => 'orderBySet')
        );
    }

    /**
     * Retrieve possible exports as a selectable option list.
     *
     * @return array $options An associative array of the format.
     */
    protected function _getexportOptions()
    {
        $options = array(
            '' => __('Normal display'),
            'csv' => __('csv (with tabulations)'),
            'ods' => __('ods (OpenDocument Spreasheet)'),
            'fods' => __('fods (Flat OpenDocument Spreadsheet)'),
        );

        $zipProcessor = $this->_getZipProcessor();
        if (!$zipProcessor) {
            unset($options['ods']);
        }

        return $options;
    }

    /**
     * Check if the server support zip and return the method used.
     *
     * @return bool
     */
    protected function _getZipProcessor()
    {
        if (class_exists('ZipArchive') && method_exists('ZipArchive', 'setCompressionName')) {
            return 'ZipArchive';
        }

        // Test the zip command line via  the processor of ExternalImageMagick.
        try {
            $cmd = 'which zip';
            Omeka_File_Derivative_Strategy_ExternalImageMagick::executeCommand($cmd, $status, $output, $errors);
            return $status == 0 ? trim($output) : false;
        } catch (Exception $e) {
            return false;
        }
    }
}
