<?php

/*·************************************************************************
 * Copyright ©2007-2011 Pieter van Beek, Almere, The Netherlands
 * 		    <http://purl.org/net/6086052759deb18f4c0c9fb2c3d3e83e>
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at <http://www.apache.org/licenses/LICENSE-2.0>
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * $Id: davacl_element_supported_privilege.php 3349 2011-07-28 13:04:24Z pieterb $
 **************************************************************************/

/**
 * File documentation (who cares)
 * @package DAVACL
 */


// <!ELEMENT supported-privilege
//  (privilege, abstract?, description, supported-privilege*)>
// <!ELEMENT privilege ANY>


/**
 * Helper class.
 * @package DAVACL
 */
class DAVACL_Element_supported_privilege {


  /**
   * @var  string  The name of the privilege 
   */
private $privilege;


/**
 * @var bool  Whether the privilege is abstract or not
 */
private $abstract;


/**
 * @var string  Description of the privilege
 */
private $description;


/**
 * @var array of DAVACL_Element_supported_privilege objects
 */
private $supported_privileges = array();


/**
 * Add a supported privilege to this element
 * 
 * @param DAVACL_Element_supported_privilege $supported_privilege
 * @return DAVACL_Element_supported_privilege $this
 */
public function add_supported_privilege($supported_privilege) {
  $this->supported_privileges[] = $supported_privilege;
  return $this;
}


/**
 * Constructor
 * 
 * @param  string   $privilege    The name of the privilege: 'namespace privilegename'
 * @param  boolean  $abstract     Whether the privilege is abstract or not
 * @param  string   $description  Description of the privilege
 */
public function __construct($privilege, $abstract, $description) {
  $this->privilege = "$privilege";
  $this->abstract = (bool)$abstract;
  $this->description = "$description";
}


/**
 * Registers the namespace of this privilege and all its 'child' privileges
 * 
 * @param   DAV_Namespaces  $namespaces  The DAV_Namespaces instance to register the namespace to
 * @return  void
 */
private function namespaces(&$namespaces) {
  $privilege = explode(' ', $this->privilege);
  $namespaces->prefix($privilege[0]);
  foreach($this->supported_privileges as $sp)
    $sp->namespaces($namespaces);
}


/**
 * Create propper XML to send to the client
 * @param DAV_Namespaces $namespaces  A DAV_Namespaces object with namespace aliases if they are already created
 * @return string
 */
public function toXML($namespaces = false) {
  if (! $namespaces) {
    $namespaces = new DAV_Namespaces();
    $this->namespaces($namespaces);
    $t_namespaces = $namespaces->toXML();
  } else
    $t_namespaces = '';
  $t_privilege = explode(' ', $this->privilege);
  $t_privilege = '<' . $namespaces->prefix($t_privilege[0]) . $t_privilege[1] . '/>';
  $t_abstract = $this->abstract ? "\n<D:abstract/>" : '';
  $t_description = DAV::xmlescape($this->description);
  $t_supported_privileges = '';
  foreach ( $this->supported_privileges as $sp )
    $t_supported_privileges .= "\n" . $sp->toXML($namespaces);
  return <<<EOS
<D:supported-privilege$t_namespaces>
<D:privilege>$t_privilege</D:privilege>$t_abstract
<D:description>$t_description</D:description>$t_supported_privileges
</D:supported-privilege>
EOS;
}


/**
 * Flattens an array of DAVACL_Element_supported_privilege to a format which is more handy to process (apparently)
 * @param array $sps array of DAVACL_Element_supported_privilege
 * @return an array of privilege => array with keys:
 * - 'children' => an array of self + all children, subchildren etc.
 * - 'abstract' => boolean
 */
public static function flatten($sps) {
  $retval = array();
  foreach ($sps as $sp) {
    $children = self::flatten($sp->supported_privileges);

    # Add sub-privileges according to RFC3744 §3.12
    if ( $sp->privilege === DAVACL::PRIV_WRITE )
      foreach( array( DAVACL::PRIV_WRITE_CONTENT, DAVACL::PRIV_WRITE_PROPERTIES,
                      DAVACL::PRIV_BIND, DAVACL::PRIV_UNBIND ) as $priv )
        if (!isset($children[$priv]))
          $children[$priv] = array( 'children' => array($priv), 'abstract' => true );

    $retval = array_merge($retval, $children);

    $descendants = array( $sp->privilege );
    foreach ($children as $child)
      $descendants = array_merge($descendants, $child['children']);

    $retval[$sp->privilege] = array(
      'children' => $descendants,
      'abstract' => $sp->abstract
    );
  }
  return $retval;
}


} // class DAV_Element_supported_privilege
