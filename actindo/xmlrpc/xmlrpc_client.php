<?php

define( 'ACTINDO_SCHEME', 'https' );
define( 'ACTINDO_HOST', 'www.actindo.biz' );
//define( 'ACTINDO_SCHEME', 'http' );
//define( 'ACTINDO_HOST', '172.16.0.66' );

define( 'ACTINDO_BASEURL', ACTINDO_SCHEME.'://'.ACTINDO_HOST.'/actindo/' );

require_once( 'xmlrpc.inc' );
require_once( 'util.php' );
require_once( 'error.php' );

class actindo_func
{
  var $logged_in;
  var $_status_func;
  var $url_path;
  var $url_host;
  var $url_port;
  var $method;
  var $url;
  var $compression;
  var $timeout=0;
  var $settings_maxage=1800;

  var $debug=0;

  var $errno;
  var $error;

  /** session-id we get from actindo */
  var $sid;

  function actindo_func( $status_func=null )
  {
    $this->_status_func = $status_func;

    $this->debug = 0;

    if( 1 )
    {
      $this->url = ACTINDO_BASEURL.'xmlrpc.php';
      $this->url_path = '/actindo/xmlrpc.php';
      $this->url_host = ACTINDO_HOST;
      $this->url_port = ACTINDO_SCHEME == 'https' ? 443 : 80;
      $this->method = ACTINDO_SCHEME == 'https' ? 'https' : 'http11';
      $this->compression = 0;
    }
  }

  function faultCode()
  {
    return $this->errno;
  }
  function faultString()
  {
    return $this->error;
  }

  function curl_errno()
  {
    if( $this->errno == $GLOBALS['xmlrpcerr']['curl_fail'] )
    {
      preg_match( "/\((\d+)\)$/", $this->error, $matches );
      return (int)$matches[1];
    }
    return 0;
  }

  function curl_error()
  {
  }

  function get_method( )
  {
    return $this->method;
  }
  function get_url_host( )
  {
    return $this->url_host;
  }
  function get_url_path( )
  {
    return $this->url_path;
  }
  function get_url( )
  {
    return $this->url;
  }


  function &_create_client( )
  {
    $cl = new xmlrpc_client( $this->url_path, $this->url_host, $this->url_port );

    $cl->setDebug( (($this->debug & 2) ? 2 : 0) );
    if( $this->compression && !$this->debug )
    {
      $cl->setRequestCompression( 'gzip' );
      $cl->setAcceptedCompression( 'gzip' );
      $cl->force_http10 = 1;
    }
    else
    {
      $cl->setRequestCompression( '' );
      $cl->setAcceptedCompression( 'identity' );
      $cl->force_http10 = 1;
    }
    /** @todo */
//    $cl->setCaCertificate( '/etc/ssl/certs', FALSE );
//    $cl->setSSLVerifyHost( 2 );
//    $cl->setSSLVerifyPeer( 0 );

    $GLOBALS['xmlrpcName'] = 'actindo xtc';

    return $cl;
  }


  function login( $username, $password, $mand_id, $using_token=FALSE )
  {
    $msg = new xmlrpcmsg( $using_token ? 'auth__token_login' : 'auth__login', array(
      new xmlrpcval( $username, 'string' ),
      new xmlrpcval( $password, 'string' ),
      new xmlrpcval( $mand_id, 'int' ),
    ) );
    $cl = $this->_create_client( FALSE );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }
    $ret = php_xmlrpc_decode( $r->val );

    $this->sid = $ret;
    return 0;
  }


  function logout( )
  {
    if( empty($this->sid) )
      return ENOTLOGGEDIN;

    $cl = $this->_create_client( FALSE );

    $msg = new xmlrpcmsg( 'auth__logout', array(
      new xmlrpcval( $this->sid, 'string' ),
    ) );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }

    return 0;
  }


  function get_user_properties( )
  {
    if( empty($this->sid) )
      return ENOTLOGGEDIN;

    $cl = $this->_create_client( FALSE );

    $msg = new xmlrpcmsg( 'auth__get_user_properties', array(
      new xmlrpcval( $this->sid, 'string' ),
    ) );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }
    $ret = php_xmlrpc_decode( $r->val );

    return $ret;
  }

  function get_application_sid( )
  {
    if( empty($this->sid) )
      return ENOTLOGGEDIN;

    $cl = $this->_create_client( FALSE );

    $msg = new xmlrpcmsg( 'auth__get_application_sid', array(
      new xmlrpcval( $this->sid, 'string' ),
    ) );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }
    $ret = php_xmlrpc_decode( $r->val );

    return $ret;
  }


  function get_token( )
  {
    if( empty($this->sid) )
      return ENOTLOGGEDIN;

    $cl = $this->_create_client( FALSE );

    $msg = new xmlrpcmsg( 'auth__get_token', array(
      new xmlrpcval( $this->sid, 'string' ),
    ) );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }
    $ret = php_xmlrpc_decode( $r->val );

    return $ret;
  }


  function ping( )
  {
    if( empty($this->sid) )
      return ENOTLOGGEDIN;

    $cl = $this->_create_client( FALSE );

    $msg = new xmlrpcmsg( 'auth__ping', array(
      new xmlrpcval( $this->sid, 'string' ),
    ) );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }
    $ret = php_xmlrpc_decode( $r->val );

    return $ret;
  }


  function check_shop_connection( $shop_id )
  {
    if( empty($this->sid) )
      return ENOTLOGGEDIN;

    $cl = $this->_create_client( FALSE );

    $msg = new xmlrpcmsg( 'shop__check_shop_connection', array(
      new xmlrpcval( $this->sid, 'string' ),
      new xmlrpcval( $shop_id, 'string' ),
    ) );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }
    $ret = php_xmlrpc_decode( $r->val );
  }


  function product_search( $bill_id=0, $deb_kred_id=0, $currency='', $date='', $wg_id=0, $type='Lief', $request=array() )
  {
    if( empty($this->sid) )
      return ENOTLOGGEDIN;

    $cl = $this->_create_client( FALSE );

    $msg = new xmlrpcmsg( 'product__search', array(
      new xmlrpcval( $this->sid, 'string' ),
      new xmlrpcval( $bill_id, 'int' ),
      new xmlrpcval( $deb_kred_id, 'int' ),
      new xmlrpcval( $currency, 'string' ),
      new xmlrpcval( $date, 'string' ),
      new xmlrpcval( $wg_id, 'int' ),
      new xmlrpcval( $type, 'string' ),
      $this->encode_all_base64( $request ),
    ) );
    $r = $cl->send( $msg, $this->timeout, $this->method );
    if( $r->faultCode() == ENOENT )
      return ENOENT;
    elseif( $r->faultCode() )
    {
      $this->errno = $r->faultCode();
      $this->error = $r->faultString();
      if( $this->debug )
        printf( "get_version FAULT 2: 0x%X (%d)\n", $r->faultCode(), $r->faultCode() );
      return $r->faultCode();
    }
    $ret = php_xmlrpc_decode( $r->val );

    return $ret;
  }


  function _set_status( $status, $percent=null )
  {
    if( is_array($this->_status_func) || is_string($this->_status_func) )
      call_user_func( $this->_status_func, $status, $percent );
  }


  /**
   * @private
   */
  function encode_all_base64( $data )
  {
    if( is_array($data) )
    {
      if( count($data) )
      {
        foreach( $data as $idx => $val )
          $ret[$idx] = $this->encode_all_base64( $val );
      }
      else
        $ret = array();
    }
    else
    {
      if( is_numeric($data) )
        $ret = $data;
      else
        $ret = new xmlrpcval( $data, $GLOBALS["xmlrpcBase64"] );
    }

    return $ret;
  }

}


?>