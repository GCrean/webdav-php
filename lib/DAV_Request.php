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
 * $Id: dav_request.php 3364 2011-08-04 14:11:03Z pieterb $
 **************************************************************************/

/**
 * File documentation (who cares)
 * @package DAV
 */

/**
 * Abstract base class for all Request classes.
 * @package DAV
 */
abstract class DAV_Request {
// Call graph:
// __construct()
// `- init_if_header()
//    `- if_header_lexer()
//
// handleRequest()
// |- check_if_headers()
// |  |- shallowLock()
// |  |- check_if_match_header()
// |  |- check_if_modified_since_header()
// |  `- check_if_header();
// |- handle()
// `- shallowUnlock()


/**
 * @var array  All methods recognized by this server
 */
static $ALLOWED_METHODS = array(
  'ACL', 'COPY', 'DELETE', 'GET', 'HEAD', 'LOCK', 'MKCOL', 'MOVE', 'OPTIONS',
  'POST', 'PROPFIND', 'PROPPATCH', 'PUT', 'REPORT', 'UNLOCK'
);


/**
 * Returns the only instance of the correct child class
 * @return DAV_Request or null if some error occured.
 * @throws void
 */
public static function inst() {
  $cache = DAV_Cache::inst( 'DAV_Request' );
  $inst = $cache->get( 'inst' );
  if ( is_null( $inst ) ) {
    try {
      $REQUEST_METHOD = strtoupper($_SERVER['REQUEST_METHOD']);
      if ( in_array( $REQUEST_METHOD, self::$ALLOWED_METHODS ) ) {
        $classname = "DAV_Request_{$REQUEST_METHOD}";
        $inst = new $classname();
      }
      else
        $inst = new DAV_Request_DEFAULT();
    }
    catch (DAV_Status $e) {
      if (! $e instanceof DAV_Status)
        $e = new DAV_Status(
          DAV::HTTP_INTERNAL_SERVER_ERROR,
          "$e"
        );
      $e->output();
    }
    $cache->set( 'inst', $inst );
  }
  return $inst;
}


/**
 * Returns the request body
 * @return string
 */
protected static function inputstring() {
  static $inputstring = null;
  if (is_null($inputstring))
    $inputstring = file_get_contents('php://input');
  return $inputstring;
}


/**
 * Returns the value of the Destination header
 * @return string either an (internal) path or an external URI.
 */
public static function destination() {
  $cache = DAV_Cache::inst( 'DAV_Request' );
  $destinationCache = $cache->get( 'destinationCache' );
  if ( is_null( $destinationCache ) ) {
    $destinationCache = @$_SERVER['HTTP_DESTINATION'] ?
      DAV::parseURI( urldecode( $_SERVER['HTTP_DESTINATION'] ), false) : false;
    $cache->get( 'destinationCache', $destinationCache );
  }
  return ( $destinationCache === false ? null : $destinationCache );
}


/**
 * Determines whether the destination can be overwritten
 * @return  boolean  True if the overwrite header indicates that the destination can be overwritten
 */
public function overwrite() {
  return ('F' !== @$_SERVER['HTTP_OVERWRITE'] );
}


/**
 * Returns the Depth header
 * @return  mixed  The value of the Depth header
 * @throws  DAV_Status  A '400 BAD REQUEST' status is returned if an invalid value was specified in the request header
 */
public function depth() {
  switch ( @$_SERVER['HTTP_DEPTH'] ) {
  case null:
  case DAV::DEPTH_0:
  case DAV::DEPTH_1:
  case DAV::DEPTH_INF:
    return @$_SERVER['HTTP_DEPTH'];
  default:
    throw new DAV_Status(
      DAV::HTTP_BAD_REQUEST,
      'Illegal value for Depth: request header: ' . @$_SERVER['HTTP_DEPTH']
    );
  }
}


/**
 * Parse the HTTP If header
 * 
 * @param  string  $pos  The position within the if header to continue parsing
 * @return array|null   next token (type and value)
 * @throws DAV_Status on lexer error
 */
static private function if_header_lexer(&$pos)
{
  // skip whitespace
  while ( strpos(" \r\n\t", substr($_SERVER['HTTP_IF'], $pos, 1) ) !== false ) $pos++;

  // already at end of string?
  if (strlen($_SERVER['HTTP_IF']) <= $pos)
    return null;

  // get next character
  $c = $_SERVER['HTTP_IF'][$pos++];

  // now it depends on what we found
  switch ($c) {
  case '<':
    // URIs are enclosed in <...>
    $pos2 = strpos($_SERVER['HTTP_IF'], '>', $pos);
    if ($pos2 === false)
      throw new DAV_Status( DAV::HTTP_BAD_REQUEST, 'Bad URI in If: header' );
    $uri = substr($_SERVER['HTTP_IF'], $pos, $pos2 - $pos);
    $pos = ++$pos2;
    return array('URI', trim($uri));

  case '[':
    //ETags are enclosed in [...]
    if (!preg_match( '@^\\s*((?:W/)?"(?:[^"\\\\]|\\\\.)*")\\s*\\]@',
                     substr($_SERVER['HTTP_IF'], $pos), $matches))
      throw new DAV_Status( DAV::HTTP_BAD_REQUEST, 'Bad ETag in If: header' );
    $etag = $matches[1];
    $pos += strlen($matches[0]);
    return array('ETAG', trim($etag));

  case 'N':
    if ( substr( $_SERVER['HTTP_IF'], $pos, 2 ) === 'ot' ) {
      // "Not" indicates negation
      $pos += 2;
      return array('NOT', 'Not');
    }

  default:
    // anything else is passed verbatim char by char
    return array('CHAR', $c);
  }
}


/**
 * Parsed If: header.
 * @var array with elements 'etag', 'notetags', 'lock' and 'notlocks'.
 */
public $if_header = array();


/**
 * Parses the If: header.
 * Puts its results into $this->if_header.
 * @return void
 * @throws DAV_Status if there's a parse error.
 */
private function init_if_header()
{
  if ( !isset( $_SERVER['HTTP_IF'] ) ) return;

  $pos = 0;

  // Outer parser loop. Iterates over (No-)Tag-Lists
  while ( ( $token = self::if_header_lexer($pos) ) ) {

    $path = DAV::getPath();
    // check for URI
    if ($token[0] === 'URI') {
      // It's a tagged list!
      $path = DAV::parseURI($token[1]); // May throw an exception
      if ( !( $token = self::if_header_lexer($pos) ) )
        throw new DAV_Status(
          DAV::HTTP_BAD_REQUEST, "Unexpected end of If: header: {$_SERVER['HTTP_IF']}"
        );
    }

    // sanity check
    if ($token[0] !== "CHAR" || $token[1] !== '(') {
      throw new DAV_Status(
        DAV::HTTP_BAD_REQUEST,
        "Error while parsing If: header: Found '{$token[1]}' where '(' was expected."
      );
    }

    // Initialize inner parser loop:
    $etag = null;
    $notetags = $locks = $notlocks = array();

    // Inner parser loop:
    while ( ( $token = self::if_header_lexer($pos) ) &&
            !( $token[0] === 'CHAR' &&
               $token[1] === ')' ) ) {

      // Initialize $bool:
      if ( $token[0] === 'NOT' ) {
        $bool = false;
        if ( !( $token = self::if_header_lexer($pos) ) )
          throw new DAV_Status(
            DAV::HTTP_BAD_REQUEST,
            "Unexpected end header If: {$_SERVER['HTTP_IF']}"
          );
      } else {
        $bool = true;
      }

      switch($token[0]) {

      case 'URI':
        DAV::$SUBMITTEDTOKENS[$token[1]] = $token[1];
        if ( $bool )
          $locks[$token[1]] = $token[1];
        else
          $notlocks[$token[1]] = $token[1];
        break;

      case 'ETAG':
        if ( $bool && $etag )
            throw new DAV_Status(DAV::HTTP_BAD_REQUEST, 'Multiple etags required on resource.');
        if ( $bool )
          $etag = $token[1];
        else
          $notetags[$token[1]] = $token[1];
        break;

      default:
        throw new DAV_Status(DAV::HTTP_BAD_REQUEST, <<<EOS
Error while parsing If: header:
Found "{$token[1]}" where "<" or "[" was expected.
EOS
        );

      } // switch($token[0])

    } // while

    // Shared locks are not supported, so any request with multiple lock tokens
    // for one URI can never succeed.
    if ( 1 < count($locks) )
      throw new DAV_Status(
        DAV::HTTP_PRECONDITION_FAILED,
        DAV::COND_LOCK_TOKEN_MATCHES_REQUEST_URI
      );

    $this->if_header[$path] = array(
      'etag' => $etag,
      'notetags' => $notetags,
      'lock' => array_shift($locks),
      'notlocks' => $notlocks
    );

  } // while
}


/**
 * The constructor
 *
 * @param string $path
 * @throws DAV_Status
 */
protected function __construct()
{
  // Prevent warning in litmus check 'delete_fragment'.
  // Should we really do this for all requests? What does the WebDAV spec
  // say about fragments?
  if (strstr($_SERVER['REQUEST_URI'], '#') !== false)
    throw new DAV_Status(DAV::HTTP_BAD_REQUEST, 'Fragments are not allowed.');

  $this->init_if_header();
}


/**
 * This method is called to handle the request.
 * 
 * This method should be implemented by a child class to handle the request the
 * propper way. The depends on the request method (GET, POST, PUT, ...).
 * 
 * @param Resource $resource
 * @return bool
 * @throws DAV_Status
 */
abstract protected function handle( $resource );


/**
 * Serve WebDAV HTTP request.
 * @param DAV_Registry $registry
 */
public function handleRequest()
{
  // We want to catch every exception thrown in this code, and report about it
  // to the user appropriately.
  $shallow_lock = false;
  try {
    $shallow_lock = $this->check_if_headers();

    $resource = DAV::$REGISTRY->resource( DAV::getPath() );
    if ( !$resource || !$resource->isVisible() and
         in_array( $_SERVER['REQUEST_METHOD'], array(
             'ACL', 'COPY', 'DELETE', 'GET', 'HEAD', 'MOVE', 'OPTIONS',
             'POST', 'PROPFIND', 'PROPPATCH', 'REPORT', 'UNLOCK',
       ) ) )
    {
      throw new DAV_Status( DAV::HTTP_NOT_FOUND );
    }

    if ( '/' !== substr( DAV::getPath(), -1 ) &&
         ( $resource &&
           $resource instanceof DAV_Collection ||
           'MKCOL' === $_SERVER['REQUEST_METHOD'] ) ) {
      $newPath = DAV::getPath() . '/';
      DAV::setPath( $newPath );
      DAV::header( array( 'Content-Location' => DAV::path2uri( $newPath ) ) );
    }

    $this->handle( $resource );
  }
  catch (Exception $e) {
    if (! $e instanceof DAV_Status)
      $e = new DAV_Status(
        DAV::HTTP_INTERNAL_SERVER_ERROR,
        "$e"
      );
    $e->output();
  }
  if ($shallow_lock)
    DAV::$REGISTRY->shallowUnlock();
  if (DAV_Multistatus::active())
    DAV_Multistatus::inst()->close();
}


/**
 * Check any HTTP If-* header applicable with the current request method
 * 
 * @return boolean TRUE if shallowLock() was called;
 */
private function check_if_headers() {
  $write_locks = $read_locks = array();
  switch($_SERVER['REQUEST_METHOD']) {
    case 'ACL':
    case 'DELETE':
    case 'LOCK':
    case 'MKCOL':
    case 'POST':
    case 'PROPPATCH':
    case 'PUT':
    case 'UNLOCK':
      // For all actions above you need a write lock for the path/resource
      $write_locks[DAV::unslashify(DAV::getPath())] = 1;
      break;
    case 'COPY':
    case 'MOVE':
      // Here, things get a bit more complicated
      if (!$this->destination())
        throw new DAV_Status(
          DAV::HTTP_BAD_REQUEST,
          'Missing required Destination: header'
        );
      if ( '/' === substr( $this->destination(), 0, 1 ) ) // For destinations as absolute paths, you'll need a write lock on the destination
        $write_locks[ DAV::unslashify( $this->destination() ) ] = 1;
      if ('COPY' === $_SERVER['REQUEST_METHOD']) // If you want to copy, you'll just need a read lock on the source
        $read_locks[DAV::unslashify(DAV::getPath())] = 1;
      else // But if you want to move, you'll need a write lock on the source (as it will be deleted)
        $write_locks[DAV::unslashify(DAV::getPath())] = 1;
      break;
  }

  // If there are write locks, you'll also need read locks on all parents
  if ( !empty($write_locks) )
    foreach (array_keys($write_locks) as $p) {
      while ($p !== '/') {
        $p = dirname($p);
        $read_locks[$p] = 1;
      }
    }

  foreach( array( 'MATCH', 'UNMODIFIED_SINCE' ) as $value ) // Conditions 'NONE_MATCH', 'MODIFIED_SINCE' are not relevant
    if ( isset( $_SERVER['HTTP_IF_' . $value] ) ) {
      $read_locks[DAV::unslashify(DAV::getPath())] = 1;
      break;
    }

  foreach (array_keys($this->if_header) as $path)
    $read_locks[DAV::unslashify($path)] = 1;

  // If we already want a write lock, we should not also want a read lock
  foreach (array_keys($write_locks) as $path)
    unset( $read_locks[$path] );

  // No locks required? Than just return false so the caller knows that there are no (shallow) locks set
  if (empty($write_locks) && empty($read_locks))
    return false;

  // Get the (shallow) locks
  DAV::$REGISTRY->shallowLock(
    array_keys( $write_locks ),
    array_keys( $read_locks )
  );
  try { // to guarantee unlocking i.c.o. exceptions
    $this->check_if_match_header();
    $this->check_if_modified_since_header();
    $this->check_if_header();
  }
  catch(Exception $e) {
    DAV::$REGISTRY->shallowUnlock();
    throw $e;
  }
  return true;
}


/**
 * Parses AND checks the If: header
 * @return void
 * @throws DAV_Status
 */
private function check_if_header()
{
  if (empty($this->if_header)) return;

  $anyStateMatches = false;
  foreach ($this->if_header as $path => $values) {
    $resource = DAV::$REGISTRY->resource($path); // May return null
    if ($resource && $resource->isVisible()) {
      $res_etag = $resource->user_prop_getetag();

      // Check etag:
      if ( $values['etag'] &&
           !self::equalETags(
              $values['etag'],
              $res_etag
            ) )
        continue;

      // Check notetags:
      if ( $res_etag && isset($values['notetags'][$res_etag]) )
        continue;

      // Check locks:
      $lock = DAV::$LOCKPROVIDER ? DAV::$LOCKPROVIDER->getlock($path) : null;
      if ( $values['lock'] and
           !$lock || $values['lock'] !== $lock->locktoken )
        continue;

      // Check notlocks:
      if ( $lock && isset( $values['notlocks'][$lock->locktoken] ) )
        continue;
    }
    elseif ( $values['etag'] || $values['lock'] )
      continue;
    $anyStateMatches = true;
  } // foreach()
  if (!$anyStateMatches)
    throw new DAV_Status(DAV::HTTP_PRECONDITION_FAILED);
} // function check_if_header()


/**
 * Parses AND checks the If-(Un)Modified-Since: header.
 * @throws DAV_Status particularly 304 Not Modified and 412 Precondition Failed
 * @return void
 */
private function check_if_modified_since_header() {
  $resource = DAV::$REGISTRY->resource(DAV::getPath());
  if ( !$resource || !$resource->isVisible() )
    return;
  if ( !( $lastModified = $resource->user_prop_getlastmodified() ) )
    return;
  if ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
       ($when = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) &&
       $lastModified <= $when )
    throw new DAV_Status(DAV::HTTP_NOT_MODIFIED);
  if ( isset($_SERVER['HTTP_IF_UNMODIFIED_SINCE']) &&
       ($when = strtotime($_SERVER['HTTP_IF_UNMODIFIED_SINCE'])) &&
       $lastModified > $when )
    throw new DAV_Status(DAV::HTTP_PRECONDITION_FAILED);
}


/**
 * Check the HTTP If-Match header
 * 
 * @return void
 */
private function check_if_match_header() {
  if ( isset( $_SERVER['HTTP_IF_MATCH'] ) ) {
    $header = $_SERVER['HTTP_IF_MATCH'];
    $none = false;
  }
  elseif ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
    $header = $_SERVER['HTTP_IF_NONE_MATCH'];
    $none = true;
  }
  else return;

  $resource = DAV::$REGISTRY->resource( DAV::getPath() );

  // The simplest case: just an asterisk '*'.
  if ( preg_match( '@^\\s*\\*\\s*$@', $header ) ) {
    if ( ( !$resource || !$resource->isVisible() ) && !$none )
      throw new DAV_Status(DAV::HTTP_PRECONDITION_FAILED, 'If-Match');
    if ( $resource && $resource->isVisible() && $none )
      throw new DAV_Status(DAV::HTTP_PRECONDITION_FAILED, 'If-None-Match');
    return;
  }

  // A list of entity-tags
  $header .= ',';
  preg_match_all( '@((?:W/)?"(?:[^"\\\\]|\\\\.)*")\\s*,@',
                  $header, $matches );
  $etags = $matches[1];
  if (!count($etags))
    throw new DAV_Status(
      DAV::HTTP_BAD_REQUEST,
      'Couldn\'t parse If-(None-)Match header.'
    );

  if ( ( !$resource || !$resource->isVisible() ) && !$none)
    throw new DAV_Status(DAV::HTTP_PRECONDITION_FAILED, 'If-Match');
  // $resource exists:
  $resource_etag = $resource->user_prop_getetag();
  if ($none) {
    foreach ($etags as $etag)
      if (self::equalETags($resource_etag, $etag))
        throw new DAV_Status(DAV::HTTP_PRECONDITION_FAILED, 'If-None-Match');
  }
  else {
    foreach ($etags as $etag)
      if (self::equalETags($resource_etag, $etag))
        return;
    throw new DAV_Status(DAV::HTTP_PRECONDITION_FAILED, 'If-Match');
  }
}


/**
 * Compares two ETag values.
 * @param string $a
 * @param string $b
 * @return mixed null if both ETags are malformed, true if equal, otherwise false
 */
public static function equalETags( $a, $b ) {
  $a = preg_match( '@^\\s*(W/)?("(?:[^"\\\\]|\\\\.)*")\\s*@',
                   $a, $a_matches );
  $b = preg_match( '@^\\s*(W/)?("(?:[^"\\\\]|\\\\.)*")\\s*@',
                   $b, $b_matches );
  if ( !$a && !$b )
    throw new DAV_Status(
      DAV::HTTP_BAD_REQUEST,
      'Malformed ETag(s)'
    );
  return ( $a_matches[2] === $b_matches[2] );
}


} // class DAV_Request

// End of file