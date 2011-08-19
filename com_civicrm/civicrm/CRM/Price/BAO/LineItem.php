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

/**
 *
 * @package CRM
 * @author Marshal Newrock <marshal@idealso.com>
 * $Id$
 */

require_once 'CRM/Price/DAO/LineItem.php';

/**
 * Business objects for Line Items generated by monetary transactions
 */
class CRM_Price_BAO_LineItem extends CRM_Price_DAO_LineItem 
{
    /**
     * Creates a new entry in the database.
     *
     * @param array $params (reference) an assoc array of name/value pairs
     *
     * @return object CRM_Price_DAO_LineItem object
     * @access public
     * @static
     */
    static function create ( &$params )
    {
        $lineItemBAO = new CRM_Price_BAO_LineItem( );
        $lineItemBAO->copyValues( $params );
        return $lineItemBAO->save( );
    }

    /**
     * Takes a bunch of params that are needed to match certain criteria and
     * retrieves the relevant objects.  Typically, the valid params are only
     * price_field_id.  This is the inverse function of create.  It also
     * stores all of the retrieved values in the default array.
     *
     * @param array $params   (reference ) an assoc array of name/value pairs
     * @param array $defaults (reference ) an assoc array to hold the flattened values
     *
     * @return object CRM_Price_BAO_LineItem object
     * @access public
     * @static
     */
    static function retrieve( &$params, &$defaults )
    {
        $lineItem = new CRM_Price_BAO_LineItem( );
        $lineItem->copyValues( $params );
        if ( $lineItem->find( true ) ) {
            CRM_Core_DAO::storeValues( $lineItem, $defaults );
            return $lineItem;
        }
        return null;
    }
    
    /**
     * Given a participant id/contribution id, 
     * return contribution/fee line items
     *
     * @param $entityId  int    participant/contribution id
     * @param $entity    string participant/contribution.
     *
     * @return array of line items
     */
    static function getLineItems( $entityId, $entity = 'participant' ) 
    {
        $selectClause = $whereClause = $fromClause = null;

        $selectClause = "
SELECT    li.id, 
          li.label, 
          li.qty, 
          li.unit_price, 
          li.line_total, 
          pf.label as field_title, 
          pf.html_type, 
          li.price_field_id,
          li.participant_count,
          li.price_field_value_id,
          pfv.description";

        $fromClause = "
FROM      civicrm_%2 as %2 
LEFT JOIN civicrm_line_item li ON ( li.entity_id = %2.id AND li.entity_table = 'civicrm_%2')
LEFT JOIN civicrm_price_field_value pfv ON ( pfv.id = li.price_field_value_id )
LEFT JOIN civicrm_price_field pf ON (pf.id = li.price_field_id )";  
        
        $whereClause = "
WHERE     %2.id = %1";
       
        $lineItems = array( );
        
        if ( !$entityId || !$entity || !$fromClause ) return $lineItems; 
        
        $params = array( 1 => array( $entityId, 'Integer' ),
                         2 => array( $entity, 'Text' ) );

        $dao = CRM_Core_DAO::executeQuery( "$selectClause $fromClause $whereClause", $params );
        while ( $dao->fetch() ) {
            if ( !$dao->id ) continue;
            $lineItems[$dao->id] = array( 'qty'                  => $dao->qty,
                                          'label'                => $dao->label,
                                          'unit_price'           => $dao->unit_price,
                                          'line_total'           => $dao->line_total,
                                          'price_field_id'       => $dao->price_field_id,
                                          'participant_count'    => $dao->participant_count,
                                          'price_field_value_id' => $dao->price_field_value_id,
                                          'field_title'          => $dao->field_title,
                                          'html_type'            => $dao->html_type,
                                          'description'          => $dao->description,
                                          );
        }
        return $lineItems;
    }

    /**
     * This method will create the lineItem array required for
     * processAmount method
     *
     * @param  int   $fid       price set field id
     * @param  array $params    referance to form values
     * @param  array $fields    referance to array of fields belonging
     *                          to the price set used for particular event
     * @param  array $values    referance to the values array(this is
     *                          lineItem array)
     *
     * @return void
     * @access static
     */
    static function format( $fid, &$params, &$fields, &$values )
    {
        if ( empty( $params["price_{$fid}"] ) ) {
            return;
        }

        $optionIDs = implode( ',', array_keys( $params["price_{$fid}"] ) );
        
        //lets first check in fun parameter,
        //since user might modified w/ hooks.
        $options = array( );
        if ( array_key_exists( 'options', $fields ) ) {
            $options = $fields['options'];
        } else {
            require_once 'CRM/Price/BAO/FieldValue.php';
            CRM_Price_BAO_FieldValue::getValues( $fid, $options, 'weight', true );
        }
        $fieldTitle = CRM_Utils_Array::value( 'label', $fields );
        if ( !$fieldTitle ) {
            $fieldTitle = CRM_Core_DAO::getFieldValue( 'CRM_Price_DAO_Field', $fid, 'label' );
        }
        
        foreach( $params["price_{$fid}"] as $oid => $qty ) {
            require_once 'CRM/Price/BAO/Field.php';
            $price = $options[$oid]['amount'];
            $participantsPerField = CRM_Utils_Array::value( 'count', $options[$oid], 0 );
            
            $values[$oid] = array( 'price_field_id'       => $fid,
                                   'price_field_value_id' => $oid,
                                   'label'                => $options[$oid]['label'],
                                   'field_title'          => $fieldTitle,
                                   'description'          => CRM_Utils_Array::value('description', $options[$oid]),
                                   'qty'                  => $qty,
                                   'unit_price'           => $price,
                                   'line_total'           => $qty * $price,
                                   'participant_count'    => $qty * $participantsPerField,
                                   'max_value'            => CRM_Utils_Array::value( 'max_value', $options[$oid] ),
                                   'html_type'            => $fields['html_type'] );
        }
    }
    
    /**
     * Delete line items for given entity.
     *
     * @param int $entityId
     * @param int $entityTable
     *
     * @access public
     * @static
     */
    public static function deleteLineItems( $entityId, $entityTable )
    {
        $result = false;
        if ( !$entityId || !$entityTable ) {
            return $result;
        }
        
        if ( $entityId && !is_array( $entityId ) ) {
            $entityId = array( $entityId );
        }
        
        $query = "DELETE FROM civicrm_line_item where entity_id IN ('" . implode( "','" , $entityId ) . "') AND entity_table = '$entityTable'";
        $dao = CRM_Core_DAO::executeQuery( $query );
        return $result;
    }
}
