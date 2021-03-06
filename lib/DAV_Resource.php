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
 * $Id: dav_resource.php 3364 2011-08-04 14:11:03Z pieterb $
 **************************************************************************/

/**
 * File documentation (who cares)
 * @package DAV
 */

/**
 * Resource to be disclosed through WebDAV
 * @package DAV
 */

class DAV_Resource {


/**
 * Asserts whether this resource is locked
 * 
 * @param string $path
 */
public function assertLock() {
  if ( !DAV::$LOCKPROVIDER ) return null;
  if ( ( $lock = DAV::$LOCKPROVIDER->getlock($this->path) ) &&
       !isset( DAV::$SUBMITTEDTOKENS[$lock->locktoken] ) )
    throw new DAV_Status(
      DAV::HTTP_LOCKED, array(
        DAV::COND_LOCK_TOKEN_SUBMITTED =>
          new DAV_Element_href( $lock->lockroot )
      )
    );
}


/**
 * Asserts whether members of this resource are locked
 * 
 * @param string $path
 * @return mixed one of the following:
 * - DAV_Element_href of the lockroot of the missing token
 * - null if no lock was found.
 */
public function assertMemberLocks() {
  if ( !DAV::$LOCKPROVIDER ) return;
  if ( ! $this instanceof DAV_Collection ) return;
  $locks = DAV::$LOCKPROVIDER->memberLocks( $this->path );
  $unsubmitted = array();
  foreach ($locks as $token => $lock)
    if ( !isset( DAV::$SUBMITTEDTOKENS[$token] ) )
      $unsubmitted[] =
        DAV::$REGISTRY->resource($lock->lockroot)->isVisible() ?
        $lock->lockroot : '/';
  if ( !empty( $unsubmitted ) )
    throw new DAV_Status(
      DAV::HTTP_LOCKED, array(
        DAV::COND_LOCK_TOKEN_SUBMITTED => new DAV_Element_href($unsubmitted)
      )
    );
}



/**
 * Gets the parent collection of this resource
 * 
 * @return  DAV_Resource  The collection containing this resource or null if this resource is the root collection
 */
public function collection() {
  return ('/' === $this->path ) ?
    null : DAV::$REGISTRY->resource(dirname($this->path));
}


/**
 * Determines whether this resource is visible to the current user
 * 
 * @return  bool  True if visible, false if invisible
 */
public function isVisible() { return true; }


/**
 * @var  string  A properly slashified path
 */
public $path;


/**
 * Constructor
 * 
 * @param  string  $path  A properly slashified path.
 */
public function __construct($path) {
  $this->path = $path;
}

/*
 * Report the capabilities of this resource.
 * Returns an binary ORed combination of the CAPABILITY_* constants defined in
 * this class.
 *
 * The default implementation returns 0.
 * @return int
 */
//public function user_capabilities() { return 0; }


/**
 * Handle the COPY request.
 * When this method is called, PRIV_READ has already been asserted, but not
 * PRIV_READ_ACL.
 * This function should call DAV_Multistatus::inst()->addStatus() to report
 * partial failure. DAV_Status Sec.9.8.5 mentions the following status codes:
 * - 403 Forbidden - also applicable if source and destination are equal,
 *   but this case is automatically handled for you.
 * - 409 Conflict - one or more intermediate collections are missing at the
 *   destination.
 * - 412 Precondition Failed - also applicable if the Overwrite: header was
 *   set to 'F' and the destination resource was mapped.
 * - 423 Locked - The destination (or members therein) are locked
 * - 507 Insufficient Storage
 * @param string $path the destination
 * @return void
 * @throws DAV_Status if the request fails entirely
 */
public function method_COPY( $path ) {
  throw new DAV_Status( DAV::HTTP_NOT_IMPLEMENTED );
}


/**
 * Handle the COPY request.
 * @param string $destination URL
 * @param bool $overwrite
 * @return bool true if the destination was overwritten, false if it was newly
 * created
 * @throws DAV_Status Sec.9.8.5 mentions the following status codes:
 * - 403 Forbidden - also applicable if source and destination are equal,
 *   but this case is automatically handled for you.
 * - 409 Conflict - one or more intermediate collections are missing at the
 *   destination.
 * - 412 Precondition Failed - also applicable if the Overwrite: header was
 *   set to 'F' and the destination resource was mapped.
 * - 423 Locked - The destination (or members therein) are locked
 * - 502 Bad Gateway - the external server cannot be reached.
 * - 507 Insufficient Storage
 */
public function method_COPY_external( $destination, $overwrite ) {
  throw new DAV_Status( DAV::HTTP_NOT_IMPLEMENTED );
}


/**
 * Handle a GET request.
 * @param Hash $headers the headers that are about to be sent.
 * @return resource|string|void a stream or a string. Alternatively, you can
 *   start streaming output from within this method. Don't forget to send the
 *   headers first, with <code>DAV::header($headers)</code>.
 * @throws DAV_Status
 */
public function method_GET() {
  throw new DAV_Status( DAV::HTTP_NOT_IMPLEMENTED );
}


/**
 * Extra HTTP headers for GET and HEAD requests.
 * The following headers are set automatically if you don't return them:
 * - Content-Type
 * - Content-Length
 * - ETag
 * - Last-Modified
 * @return array An associative array of HTTP-headers.
 */
public function method_HEAD() {
  return array();
}


/**
 * Handle an OPTIONS request
 * 
 * @param array $headers By default, DAV_Server returns same standard response
 * to an OPTIONS request. This method allows you to filter this response.
 * Especially, you may want to override the default Allow: and DAV: headers.
 * @return array the $headers array
 * @throws DAV_Status You could throw a DAV_Status if you think this is
 * appropriate, eg. because of access rights limitations.
 */
public function method_OPTIONS( $headers ) { return $headers; }


/**
 * Handle a POST request.
 * @param array $headers Headers you want to submit in the HTTP response.
 * @return mixed either a valid stream, a string, or null (in which case the
 * default status code will be '204 No Content'.
 * @throws DAV_Status
 */
public function method_POST( &$headers ) {
  throw new DAV_Status( DAV::HTTP_NOT_IMPLEMENTED );
}


/**
 * Handle a PUT request.
 * This method SHOULD be implemented by non-collections.
 * @param   resource  $stream  The input stream from which to read the data
 * @return  void
 */
public function method_PUT($stream) {
  throw new DAV_Status( DAV::HTTP_NOT_IMPLEMENTED );
}


/**
 * Handle a PUT request.
 * This method SHOULD be implemented by non-collections.
 * @param   resource $stream  The input stream from which to read the data
 * @param   int      $start
 * @param   int      $end
 * @param   int      $total
 * @return  void
 */
public function method_PUT_range( $stream, $start, $end, $total ) {
  throw new DAV_Status( DAV::HTTP_NOT_IMPLEMENTED );
}


/**
 * Gets the creationdate property in a PHP native format (instead of XML for webDAV)
 * 
 * @return int UNIX timestamp
 */
public function user_prop_creationdate()       { return null; }


/**
 * Gets the displayname property in a PHP native format (instead of XML for webDAV)
 * 
 * @return string not XML, just UTF-8 text
 */
public function user_prop_displayname()        { return null; }


/**
 * Gets the executable property in a PHP native format (instead of XML for webDAV)
 * 
 * @return bool true if executable
 */
public function user_prop_executable()         { return null; }


/**
 * Gets the getcontentlanguage property in a PHP native format (instead of XML for webDAV)
 * 
 * @return string not XML, just UTF-8 text
 */
public function user_prop_getcontentlanguage() { return null; }


/**
 * Gets the getcontentlength property in a PHP native format (instead of XML for webDAV)
 * 
 * @return string not XML, just UTF-8 text
 */
public function user_prop_getcontentlength()   { return null; }


/**
 * Gets the getcontenttype property in a PHP native format (instead of XML for webDAV)
 * 
 * @return string not XML, just UTF-8 text
 */
public function user_prop_getcontenttype()     { return null; }


/**
 * Gets the getetag property in a PHP native format (instead of XML for webDAV)
 * 
 * @return string (W/)?"<etag>" not XML, just UTF-8 text
 */
public function user_prop_getetag()            { return null; }


/**
 * Gets the getlastmodified property in a PHP native format (instead of XML for webDAV)
 * 
 * @return int UNIX timestamp
 */
public function user_prop_getlastmodified()    { return null; }


/*
 * Gets the lastaccessed property in a PHP native format (instead of XML for webDAV)
 * 
 * @return int UNIX timestamp
 */
//public function user_prop_lastaccessed()       { return null; }


/**
 * Type of resource.
 * RFC4918 defines <D:collection/>.
 * RFC3744 adds <D:principle>.
 * @return string an XML fragment
 */
public function user_prop_resourcetype()       { return null; }


/*
 * @return int one of DAV::LOCKSCOPE_NONE,      DAV::LOCKSCOPE_SHARED,
 *                    DAV::LOCKSCOPE_EXCLUSIVE, DAV::LOCKSCOPE_BOTH
 */
//public function user_prop_supportedlock()      { return null; }


/**
 * All available properties of the current resource.
 * This method must return an array with ALL property names as keys and a
 * boolean as value, indicating if the property should be returned in an
 * <allprop/> PROPFIND request.
 * @return array
 */
public function propname() {
  $retval = $this->user_propname();
  foreach ( DAV::$WEBDAV_PROPERTIES as $prop => $method )
    if ( null !== call_user_func( array( $this, "prop_$method" ) ) )
      $retval[$prop] = true;
  return $retval;
}


/**
 * By default, all properties are readable.
 * @param array $properties
 * @return array an array of (property => isReadable) pairs.
 */
public function property_priv_read($properties) {
  $retval = array();
  foreach ($properties as $prop) $retval[$prop] = true;
  return $retval;
}


/**
 * By default, all properties are not writable.
 * @param array $properties
 * @return array an array of (property => isWritable) pairs.
 */
public function property_priv_write($properties) {
  $retval = array();
  foreach ($properties as $prop) $retval[$prop] = false;
  return $retval;
}


/**
 * All available properties of the current resource.
 * This method must return an array with ALL property names as keys and a
 * boolean as value, indicating if the property should be returned in an
 * <allprop/> PROPFIND request.
 * @return array
 */
protected function user_propname() {
  return array();
}


/**
 * Gets dead property, but in XML/webDAV ready format
 * 
 * @param string $propname the name of the property to be returned,
 *        eg. "mynamespace: myprop"
 * @return string XML element
 * @throws DAV_Status if the property is not defined.
 */
protected function user_prop($propname) {
  return null;
}


/**
 * Sets dead property, but value should be in XML/webDAV ready format
 * 
 * @param string $propname the name of the property to be set.
 * @param string $value an XML fragment, or null to unset the property.
 * @return void
 * @throws DAV_Status §9.2.1 specifically mentions the following statusses.
 * - 200 (OK): The property set or change succeeded. Note that if this appears
 *   for one property, it appears for every property in the response, due to the
 *   atomicity of PROPPATCH.
 * - 403 (Forbidden): The client, for reasons the server chooses not to
 *   specify, cannot alter one of the properties.
 * - 403 (Forbidden): The client has attempted to set a protected property, such
 *   as DAV:getetag. If returning this error, the server SHOULD use the
 *   precondition code 'cannot-modify-protected-property' inside the response
 *   body.
 * - 409 (Conflict): The client has provided a value whose semantics are not
 *   appropriate for the property.
 * - 424 (Failed Dependency): The property change could not be made because of
 *   another property change that failed.
 * - 507 (Insufficient Storage): The server did not have sufficient space to
 *   record the property.
 */
protected function user_set($propname, $value = null) {
  throw new DAV_Status(DAV::HTTP_FORBIDDEN);
}


// DAV properties:

/**
 * Gets the creationdate property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_creationdate() {
  if (is_null($tmp = $this->user_prop_creationdate())) return null;
  return gmdate( 'Y-m-d\\TH:i:s\\Z', $tmp );
}


/**
 * Gets the displayname property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_displayname() {
  if (is_null($tmp = $this->user_prop_displayname())) return null;
  return DAV::xmlescape( $tmp );
}


/**
 * Sets the displayname property, but the value should be in an XML ready format for webDAV
 * 
 * @param  string  $value  The new value
 * @return string XML
 */
final public function set_displayname($value) {
  if (!is_null($value)) {
    if (false !== strpos($value, '<'))
      throw new DAV_Status(
        DAV::HTTP_BAD_REQUEST,
        'XML is not allowed in displaynames.'
      );
    $value = htmlspecialchars_decode($value);
  }
  return $this->user_set_displayname($value);
}


/**
 * Sets the displayname property with a PHP native value
 * 
 * @param  string  $value  The new value
 * @return void
 * @throws DAV_Status
 */
protected function user_set_displayname($value) {
  throw new DAV_Status( DAV::HTTP_FORBIDDEN );
}


/**
 * Gets the executable property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_executable() {
  if (is_null($tmp = $this->user_prop_executable())) return null;
  return $tmp ? 'T' : 'F';
}


/**
 * Sets the executable property, but the value should be in an XML ready format for webDAV
 * 
 * @param  string  $value  The new value
 * @return void
 * @throws DAV_Status
 */
final public function set_executable($value) {
  if (null !== $value) $value = ($value === 'T');
  return $this->user_set_executable($value);
}


/**
 * Sets the executable property with a PHP native value
 * 
 * @param  string  $value  The new value
 * @return void
 * @throws DAV_Status
 */
protected function user_set_executable($value) {
  throw new DAV_Status( DAV::HTTP_FORBIDDEN );
}


/**
 * Gets the getcontentlanguage property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_getcontentlanguage() {
  if (is_null($tmp = $this->user_prop_getcontentlanguage())) return null;
  return DAV::xmlescape($tmp);
}


/**
 * Sets the getcontentlanguage property, but the value should be in an XML ready format for webDAV
 * 
 * @param  string  $value  The new value
 * @return void
 * @throws DAV_Status
 */
final public function set_getcontentlanguage($value) {
  if (null !== $value) {
    $languages = preg_split('/,\\s*/', $value);
    foreach ($languages as $language)
      if (!preg_match('/^[a-z]{1,8}(?:-[a-z]{1,8})*$/i', $language))
        throw new DAV_Status(
          DAV::HTTP_BAD_REQUEST,
          "'$language' is not a valid HTTP/1.1 language tag."
        );
    $value = implode(', ', $languages);
  }
  return $this->user_set_getcontentlanguage($value);
}


/**
 * Sets the getcontentlanguage property with a PHP native value
 * 
 * @param  string  $value  The new value
 * @return void
 * @throws DAV_Status
 */
protected function user_set_getcontentlanguage($value) {
  throw new DAV_Status( DAV::HTTP_FORBIDDEN );
}


/**
 * Gets the getcontentlength property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_getcontentlength() {
  if (is_null($tmp = $this->user_prop_getcontentlength())) return null;
  return DAV::xmlescape($tmp);
}


/**
 * Gets the getcontenttype property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_getcontenttype() {
  if (is_null($tmp = $this->user_prop_getcontenttype())) return null;
  return DAV::xmlescape( $tmp );
}


/**
 * Sets the getcontenttype property, but the value should be in an XML ready format for webDAV
 * 
 * @param  string  $value  The new value
 * @return void
 * @throws DAV_Status
 */
final public function set_getcontenttype($value) {
  if ( !is_null( $value ) ) {
    // RFC2616 §2.2
    // token          = 1*<any CHAR except CTLs or separators>
    // separators     = "(" | ")" | "<" | ">" | "@"
    //                | "," | ";" | ":" | "\" | <">
    //                | "/" | "[" | "]" | "?" | "="
    //                | "{" | "}" | SP | HT

    // A token. Note that it's escaped for use between @@ delimiters.
    $token = "[^\\x00-\\x20\\x7f-\\xff\\(\\)<>\\@,;:\\\\\"/\\[\\]?={}]+";
    //                                   escaped^         ^unescaped
    $quoted = "\"(?:\"\"|[\\x20-\\x7e]|\\r\\n[\\t ]+)*\"";
    if ( !preg_match( "@^{$token}/{$token}\\s*(.*)\$@s", $value, $matches ) ||
         !preg_match( "@^(?:;\\s*{$token}=(?:{$quoted}|{$token})\\s*)*\$@s", $matches[1] ) )
      throw new DAV_Status(
        DAV::HTTP_BAD_REQUEST,
        "'$value' is not a valid media type."
      );
  }
  return $this->user_set_getcontenttype($value);
}


/**
 * Sets the getcontenttype property with a PHP native value
 * 
 * @param  string  $value  The new value
 * @return void
 * @throws DAV_Status
 */
protected function user_set_getcontenttype($value) {
  throw new DAV_Status( DAV::HTTP_FORBIDDEN );
}


/**
 * Gets the getetag property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_getetag() {
  if (is_null($tmp = $this->user_prop_getetag())) return null;
  return DAV::xmlescape( $tmp );
}


/**
 * Gets the getlastmodified property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_getlastmodified() {
  if (is_null($tmp = $this->user_prop_getlastmodified())) return null;
  return DAV::xmlescape( DAV::httpDate( $tmp ) );
}


/*
 * @return string XML
 */
//final public function prop_lastaccessed() {
//  if (is_null($tmp = $this->user_prop_lastaccessed())) return null;
//  return gmdate( 'Y-m-d\\TH:i:s\\Z', $tmp );
//  //return DAV::xmlescape( DAV::httpDate($tmp) );
//}


/**
 * Gets the lockdiscovery property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_lockdiscovery() {
  if ( ! DAV::$LOCKPROVIDER ) return null;
  $retval = ( $lock = DAV::$LOCKPROVIDER->getlock($this->path) ) ?
    $lock->toXML() : '';
  return $retval;
}


/**
 * Gets the resourcetype property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_resourcetype() {
  $retval = $this->user_prop_resourcetype();
  if (!is_null($retval)) return $retval;
  if ($this instanceof DAV_Collection)
    $retval .= DAV_Collection::RESOURCETYPE;
  if ($this instanceof DAVACL_Principal)
    $retval .= DAVACL_Principal::RESOURCETYPE;
  return $retval;
}


/**
 * Gets the supported_report_set property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_supported_report_set() {
  $retval = ($this instanceof DAVACL_Principal_Collection) ?
    DAV::$REPORTS :
    array(DAV::REPORT_EXPAND_PROPERTY);
  return '<D:supported-report><D:' .
    implode("/></D:supported-report>\n<D:supported-report><D:", $retval) .
    '/></D:supported-report>';
}


/**
 * Gets the supportedlock property in an XML ready format for webDAV
 * 
 * @return string XML
 */
final public function prop_supportedlock() {
  if ( ! DAV::$LOCKPROVIDER ) return null;
  return <<<EOS
<D:lockentry>
  <D:lockscope><D:exclusive/></D:lockscope>
  <D:locktype><D:write/></D:locktype>
</D:lockentry>
EOS;
}


/**
 * Gets the lockdiscovery property in an XML ready format for webDAV
 * 
 * @param string $propname the name of the property to be returned,
 *        eg. "mynamespace: myprop"
 * @return string XML or NULL if the property is not defined.
 */
public function prop($propname) {
  if ($method = @DAV::$WEBDAV_PROPERTIES[$propname])
    return call_user_func(array($this, "prop_$method"));
  return $this->user_prop($propname);
}


/**
 * Stores properties set earlier by set().
 * @return void
 * @throws DAV_Status in particular 507 (Insufficient Storage)
 */
public function storeProperties() {
  throw new DAV_Status( DAV::HTTP_FORBIDDEN );
}


/**
 * Handle a PROPPATCH request.
 * 
 * @param string $propname the name of the property to be set.
 * @param string $value an XML fragment, or null to unset the property.
 * @return void
 * @throws DAV_Status §9.2.1 specifically mentions o.a. the following statusses.
 * - 403 (Forbidden): The client, for reasons the server chooses not to
 *   specify, cannot alter one of the properties.
 * - 403 (Forbidden): The client has attempted to set a protected property, such
 *   as DAV:getetag. If returning this error, the server SHOULD use the
 *   precondition code 'cannot-modify-protected-property' inside the response
 *   body.
 * - 409 (Conflict): The client has provided a value whose semantics are not
 *   appropriate for the property.
 * - 507 (Insufficient Storage): The server did not have sufficient space to
 *   record the property.
 */
public function method_PROPPATCH($propname, $value = null) {
  if ($method = @DAV::$WEBDAV_PROPERTIES[$propname])
    return call_user_func(array($this, "set_$method"), $value);
  return $this->user_set($propname, $value);
}


} // class DAV_Resource

