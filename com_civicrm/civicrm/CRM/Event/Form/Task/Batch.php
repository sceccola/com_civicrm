<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Profile/Form.php';
require_once 'CRM/Event/Form/Task.php';

/**
 * This class provides the functionality for batch profile update for events
 */
class CRM_Event_Form_Task_Batch extends CRM_Event_Form_Task 
{
    /**
     * the title of the group
     *
     * @var string
     */
    protected $_title;

    /**
     * maximum profile fields that will be displayed
     *
     */
    protected $_maxFields = 9;

    /**
     * variable to store redirect path
     *
     */
    protected $_userContext;

    /**
     * variable to store previous status id.
     *
     */
    protected $_fromStatusIds;
    
    /**
     * build all the data structures needed to build the form
     *
     * @return void
     * @access public
     */
    function preProcess( ) 
    {
        /*
         * initialize the task and row fields
         */
        parent::preProcess( );
        
        //get the contact read only fields to display.
        require_once 'CRM/Core/BAO/Preferences.php';
        $readOnlyFields = array_merge( array( 'sort_name' => ts( 'Name' ) ),
                                       CRM_Core_BAO_Preferences::valueOptions( 'contact_autocomplete_options',
                                                                               true, null, false, 'name', true ) );
        //get the read only field data.
        $returnProperties  = array_fill_keys( array_keys( $readOnlyFields ), 1 );
        require_once 'CRM/Contact/BAO/Contact/Utils.php';
        $contactDetails = CRM_Contact_BAO_Contact_Utils::contactDetails( $this->_participantIds, 
                                                                         'CiviEvent', $returnProperties );
        $this->assign( 'contactDetails', $contactDetails );
        $this->assign( 'readOnlyFields', $readOnlyFields );             
    }
  
    /**
     * Build the form
     *
     * @access public
     * @return void
     */
    function buildQuickForm( ) 
    {    
        $ufGroupId = $this->get('ufGroupId');
        
        if ( ! $ufGroupId ) {
            CRM_Core_Error::fatal( 'ufGroupId is missing' );
        }

        require_once "CRM/Core/BAO/UFGroup.php";
        $this->_title = ts('Batch Update for Events') . ' - ' . CRM_Core_BAO_UFGroup::getTitle ( $ufGroupId );
        CRM_Utils_System::setTitle( $this->_title );
        $this->addDefaultButtons( ts('Save') );
        $this->_fields  = array( );
        $this->_fields  = CRM_Core_BAO_UFGroup::getFields( $ufGroupId, false, CRM_Core_Action::VIEW );

        // remove file type field and then limit fields
        $suppressFields = false;
        $removehtmlTypes = array( 'File', 'Autocomplete-Select' );
        foreach ($this->_fields as $name => $field ) {
            if ( $cfID = CRM_Core_BAO_CustomField::getKeyID($name) && 
                 in_array( $this->_fields[$name]['html_type'], $removehtmlTypes ) ) {                        
                $suppressFields = true;
                unset($this->_fields[$name]);
            }
            
            //fix to reduce size as we are using this field in grid
            if ( is_array( $field['attributes'] ) && $this->_fields[$name]['attributes']['size'] > 19 ) {
                //shrink class to "form-text-medium"
                $this->_fields[$name]['attributes']['size'] = 19;
            }
        }

        $this->_fields  = array_slice($this->_fields, 0, $this->_maxFields);

        $this->addButtons( array(
                                 array ( 'type'      => 'submit',
                                         'name'      => ts('Update Participant(s)'),
                                         'isDefault' => true   ),
                                 array ( 'type'      => 'cancel',
                                         'name'      => ts('Cancel') ),
                                 )
                           );
        
        
        $this->assign( 'profileTitle', $this->_title );
        $this->assign( 'componentIds', $this->_participantIds );
        $fileFieldExists = false;
        
        //load all campaigns.
        if ( array_key_exists( 'participant_campaign_id', $this->_fields ) ) {
            $this->_componentCampaigns = array( );
            CRM_Core_PseudoConstant::populate( $this->_componentCampaigns,
                                               'CRM_Event_DAO_Participant',
                                               true, 'campaign_id', 'id', 
                                               ' id IN ('. implode(' , ',array_values( $this->_participantIds ) ) .' ) ');
        }
        
        //fix for CRM-2752
        require_once "CRM/Core/BAO/CustomField.php";
        // get the option value for custom data type 	
		$this->_roleCustomDataTypeID      = CRM_Core_OptionGroup::getValue( 'custom_data_type', 'ParticipantRole', 'name' );
		$this->_eventNameCustomDataTypeID = CRM_Core_OptionGroup::getValue( 'custom_data_type', 'ParticipantEventName', 'name' );
		
		// build custom data getFields array
		$customFieldsRole  = CRM_Core_BAO_CustomField::getFields( 'Participant', false, false, null, $this->_roleCustomDataTypeID );		    
		
        $customFieldsEvent = CRM_Core_BAO_CustomField::getFields( 'Participant', false, false, null, $this->_eventNameCustomDataTypeID );
        $customFields      = CRM_Utils_Array::crmArrayMerge( $customFieldsRole, 
                                                         CRM_Core_BAO_CustomField::getFields( 'Participant', false, false, null, null, true ) );
        $this->_customFields = CRM_Utils_Array::crmArrayMerge( $customFieldsEvent, $customFields );

        foreach ( $this->_participantIds as $participantId ) {
            $roleId = CRM_Core_DAO::getFieldValue( "CRM_Event_DAO_Participant", $participantId, 'role_id' ); 
            $eventId = CRM_Core_DAO::getFieldValue( "CRM_Event_DAO_Participant", $participantId, 'event_id' ); 
            foreach ( $this->_fields as $name => $field ) {
                if ( $customFieldID = CRM_Core_BAO_CustomField::getKeyID( $name ) ) {
                    $customValue = CRM_Utils_Array::value( $customFieldID, $this->_customFields );
                    if ( CRM_Utils_Array::value( 'extends_entity_column_value', $customValue ) ) {
                        $entityColumnValue = explode( CRM_Core_DAO::VALUE_SEPARATOR, 
                                                      $customValue['extends_entity_column_value'] );
                    }
                    if ( ( $this->_roleCustomDataTypeID == $customValue['extends_entity_column_id'] ) &&
                         ( CRM_Utils_Array::value( $roleId, $entityColumnValue ) ) ) {
                        CRM_Core_BAO_UFGroup::buildProfile( $this, $field, null, $participantId );
                    } else if ( ( $this->_eventNameCustomDataTypeID == $customValue['extends_entity_column_id'] ) &&
                         ( $eventId == $entityColumnValue[$roleId] ) ) {
                        CRM_Core_BAO_UFGroup::buildProfile( $this, $field, null, $participantId );
                    } else if ( CRM_Utils_System::isNull( $entityColumnValue[$roleId] ) ) {
                        CRM_Core_BAO_UFGroup::buildProfile( $this, $field, null, $participantId );
                    }
                } else {
                    if ( $field['name'] == 'participant_role_id' ) {
                        $field['is_multiple'] = true;
                    }
                    // handle non custom fields
                    CRM_Core_BAO_UFGroup::buildProfile( $this, $field, null, $participantId );
                }
            }
        }
        
        $this->assign( 'fields', $this->_fields     );

        // don't set the status message when form is submitted.
        $buttonName = $this->controller->getButtonName('submit');

        if ( $suppressFields && $buttonName != '_qf_Batch_next' ) {
            CRM_Core_Session::setStatus( "FILE or Autocomplete Select type field(s) in the selected profile are not supported for Batch Update and have been excluded." );
        }
        
        $this->addDefaultButtons( ts( 'Update Participant(s)' ) );
    }

    /**
     * This function sets the default values for the form.
     * 
     * @access public
     * @return None
     */
    function setDefaultValues( ) 
    {
        if (empty($this->_fields)) {
            return;
        }
        
        $defaults = array( );
        foreach ($this->_participantIds as $participantId) {
            $details[$participantId] = array( );
            
            require_once 'CRM/Event/BAO/Participant.php';
            $details[$participantId] = CRM_Event_BAO_Participant::participantDetails( $participantId );
            CRM_Core_BAO_UFGroup::setProfileDefaults( null, $this->_fields, $defaults, false, $participantId, 'Event');

            //get the from status ids, CRM-4323
            if ( array_key_exists( 'participant_status', $this->_fields ) ) {
                $this->_fromStatusIds[$participantId] = 
                    CRM_Utils_Array::value( "field[$participantId][participant_status]", $defaults );
            }
            if ( array_key_exists( 'participant_role', $this->_fields ) ) {
                if ( $defaults["field[{$participantId}][participant_role]"] ) {
                    $roles = $defaults["field[{$participantId}][participant_role]"];
                    foreach ( explode( CRM_Core_DAO::VALUE_SEPARATOR, $roles ) as $k => $v ) {
                        $defaults["field[$participantId][participant_role][{$v}]"] = 1;
                    }
                    unset( $defaults["field[{$participantId}][participant_role]"] );
                }
            }
        }
        
        $this->assign('details',   $details);
        return $defaults;
    }


    /**
     * process the form after the input has been submitted and validated
     *
     * @access public
     * @return None
     */
    public function postProcess() 
    {
        $params = $this->exportValues( );
        if ( isset( $params['field'] ) ) {
            foreach ( $params['field'] as $key => $value ) {
                                
                //check for custom data
                $value['custom'] = CRM_Core_BAO_CustomField::postProcess( $value,
                                                                          CRM_Core_DAO::$_nullObject,
                                                                          $key,
                                                                          'Participant' );

                $value['id'] = $key;
                if ( $value['participant_register_date'] ) {
                    $value['register_date'] = CRM_Utils_Date::processDate( $value['participant_register_date'], $value['participant_register_date_time'] );
                } 
                
                if ( $value['participant_role'] ) {
                    $participantRoles = CRM_Event_PseudoConstant::participantRole( );
                    if ( is_array( $value['participant_role'] ) ) {
                        $value['role_id'] = implode( CRM_Core_DAO::VALUE_SEPARATOR, array_keys( $value['participant_role'] ) );   
                    } else {
                        $value['role_id'] = $value['participant_role'];
                    }
                } 

                //need to send mail when status change
                $statusChange = false;
                if ( $value['participant_status'] ) {
                    $value['status_id'] = $value['participant_status'];
                    $fromStatusId = CRM_Utils_Array::value( $key, $this->_fromStatusIds );
                    if ( !$fromStatusId ) {
                        $fromStatusId = CRM_Core_DAO::getFieldValue( 'CRM_Event_DAO_Participant', $key, 'status_id');
                    }
                    
                    if ( $fromStatusId != $value['status_id'] ) {
                        $statusChange = true;
                    }
                }
                
                if ( $value['participant_source'] ) {
                    $value['source'] = $value['participant_source'];
                }            
                unset($value['participant_register_date']);
                unset($value['participant_status']);
                unset($value['participant_source']);
                
                CRM_Event_BAO_Participant::create( $value );
                
                //need to trigger mails when we change status
                if ( $statusChange ) {
                    CRM_Event_BAO_Participant::transitionParticipants( array( $key ), $value['status_id'], $fromStatusId ); 
                    
                    //update related contribution status, CRM-4395
                    self::updatePendingOnlineContribution( $key, $value['status_id'] );
                }
            }
            CRM_Core_Session::setStatus(ts('The updates have been saved.'));
        } else {
            CRM_Core_Session::setStatus(ts('No updates have been saved.'));
        }
    }//end of function

    static function updatePendingOnlineContribution( $participantId, $statusId ) 
    {
        if ( !$participantId || !$statusId ) {
            return;
        }
        
        require_once 'CRM/Contribute/BAO/Contribution.php';
        $contributionId = CRM_Contribute_BAO_Contribution::checkOnlinePendingContribution( $participantId, 
                                                                                           'Event' );
        if ( !$contributionId ) {
            return;
        }
        
        //status rules.
        //1. participant - positive => contribution - completed.
        //2. participant - negative => contribution - cancelled.
        
        require_once 'CRM/Event/PseudoConstant.php';
        require_once 'CRM/Contribute/PseudoConstant.php';
        $positiveStatuses = CRM_Event_PseudoConstant::participantStatus( null, "class = 'Positive'" );
        $negativeStatuses = CRM_Event_PseudoConstant::participantStatus( null, "class = 'Negative'" );
        $contributionStatuses = CRM_Contribute_PseudoConstant::contributionStatus( null, 'name' );
        
        $contributionStatusId = null;
        if ( array_key_exists( $statusId, $positiveStatuses ) ) {
            $contributionStatusId = array_search( 'Completed', $contributionStatuses );   
        }
        if ( array_key_exists( $statusId, $negativeStatuses ) ) {
            $contributionStatusId = array_search( 'Cancelled', $contributionStatuses ); 
        }
        
        if ( !$contributionStatusId ) {
            return;
        }
        
        $params = array( 'component_id'           => $participantId, 
                         'componentName'          => 'Event',
                         'contribution_id'        => $contributionId, 
                         'contribution_status_id' => $contributionStatusId );
        
        //change related contribution status.
        require_once 'CRM/Core/Payment/BaseIPN.php';
        $updatedStatusId = CRM_Core_Payment_BaseIPN::updateContributionStatus( $params ); 
        
        return $updatedStatusId;
    }
}

