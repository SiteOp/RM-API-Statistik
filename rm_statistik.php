<?php
/**
 * jBackend tags plugin for Joomla
 *
 * @author selfget.com (info@selfget.com)
 * @package jBackend
 * @copyright Copyright 2015
 * @license GNU Public License
 * @version 1.0.0
 * @link http://www.selfget.com
 * @version 1.0.0
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;


class plgJBackendRm_Statistik extends JPlugin
{
  public function __construct(& $subject, $config)
  {
    parent::__construct($subject, $config);
    $this->loadLanguage();
  }

  /**
   * This is the function to generate plugin specific errors
   * The error response is an array with the following structure:
   *    array(
   *     'status' => 'ko',
   *     'error_code' => $errorCode,
   *     'error_description' => <short error description>
   *    )
   *
   * @param  string  $errorCode  The error code to generate
   *
   * @return  array  The response of type error to return
   */
  public static function generateError($errorCode)
  {
    $error = array();
    $error['status'] = 'ko';
    $error['error_code'] = $errorCode;
    switch($errorCode) {
      case 'REQ_NVAL':
        $error['status_code'] = 405;
        $error['error_description'] = 'Request not supported';
        break;
      case 'REQ_ANS':
        $error['status_code'] = 400;
        $error['error_description'] = 'Action not specified';
        break;
      case 'ID_MISSING':
        $error['status_code'] = 422;
        $error['error_description'] = 'Id is required';
        break;
      case 'STARS_NVAL':
        $error['status_code'] = 422;
        $error['error_description'] = 'Number of stars is wrong';
        break;
      case 'GRADE_NVAL':
        $error['status_code'] = 422;
        $error['error_description'] = 'Routegrad is wrong';
        break; 
      case 'DATE_NVAL':
        $error['status_code'] = 422;
        $error['error_description'] = 'Check the format of the date Y-m-d';
        break;
      case 'DATETIME_NVAL':
        $error['status_code'] = 422;
        $error['error_description'] = 'Check the format of the date Y-m-d H:i:s';
        break;
    }
    return $error;
  }


  /**
   * Anzahl Routen
   */
  public static function getCountRoutesTotal()
  {
    // DB-Query.
    $db = Factory::getDbo();

    $query = $db->getQuery(true);
    $query->select(array('COUNT(*)'))
          ->from('#__act_route')
    ->where('state = 1');

     $db->setQuery($query);
     $result = $db->loadResult();
   
     return $result;
  }

  


  /**
   * Kommentare
   */
  public function getComments()
  {
    $app = Factory::getApplication();
    
    // Eigene Anforderungsparameter abrufen
    $limit    = $app->input->get('limit',   null, 'UINT');

    // DB-Query
    $db	   = Factory::getDBO();
    $query = $db->getQuery(true);

    $query->select(array('comment','stars'))
          ->from('#__act_comment')
            ->where('state = 1')
            ->where('comment != "" ')
            ->order('created DESC')
          ->setLimit($limit); 

    $db->setQuery($query);

    return $db->loadAssocList();

  }



  /**
   * Routen
   */
  public function getRoutes()
  {
    $app = Factory::getApplication();
    
    // Eigene Anforderungsparameter abrufen
    $limit    = $app->input->get('limit',   null, 'UINT');

    // DB-Query
    $db	   = Factory::getDBO();
    $query = $db->getQuery(true);

    $query->select(array('r.id',
                         'r.name', 
                         's.settername', 
                         'r.settergrade', 
                         'g.uiaa',
                         'IFNULL(round(t.avg_stars, 1), 0) AS rating',
                         'r.setterdate'))
          ->from('#__act_route AS r')
          ->join('LEFT', '#__act_setter AS s ON s.id=r.setter')
          ->join('LEFT', '#__act_trigger_calc AS t ON t.id = r.id')
          ->join('LEFT', '#__act_grade AS g ON g.id = t.calc_grade_round')
          ->where('r.state = 1')
          ->order('r.setterdate DESC')
          ->setLimit($limit); 

    $db->setQuery($query);

    return $db->loadAssocList();
  }



  /**
   * Zusammenfügen
   */
  public function actionStatistik(&$response, &$status = null) 
  {
    
    $response = array('route' =>  self::getRoutes(),
                      'comment' => self::getComments(),
                      'routestotal' => self::getCountRoutesTotal()
                   );

    $response[] = $response;

        // Erstellen der Antwort
    $response['status'] = 'ok';

        return true;
  }




  /**
   * Fulfills requests for tags module
   *
   * @param   object    $module      The module invoked (this is the same of onRequest<Module>)
   * @param   object    $response    The response generated
   * @param   object    $status      The boundary conditions (e.g. authentication status). Useful to return additional info
   *
   * @return  boolean   true if there are no problems (status = ok), false in case of errors (status = ko)
   */
  public function onRequestRm_Statistik($module, &$response, &$status = null)
  {
    if ($module !== 'rm_statistik') return true; // Check if this is the triggered module or exit

    // Add to module call stack
    jBackendHelper::moduleStack($status, 'rm_statistik'); // Add this request to the module stack register

    // Now check the request
    // Each request must have three params, action/module/resource
    // action: one of the RESTful actions (e.g. GET, POST, ...)
    // module: is the name of the module to call (jBackend plugin) (e.g. tags)
    // resource: is the resource requested to the module (e.g. a tag for this tags module)
    $app      = Factory::getApplication();
    $action   = $app->input->getString('action');
    $resource = $app->input->getString('resource');
    // module already checked by jBackend before to dispatch the request,
    // so no needs to check the module if we are here :)

    // Check if the action is specified
    if (is_null($action)) 
    {
      $response = self::generateError('REQ_ANS'); // Action not specified
      return false;
    }

    // POST not implemented
    if ($action != 'get') 
    {
      $response = self::generateError('REQ_NVAL'); // Action not supported
      return false;
    }

    // Now we can manage any supported request. If the request doesn't match any case the function return just true
    // jBackend initializes the response to null so if no plugin matches the request the final result is still null
    // and the exception can be managed by jBackend itself
    switch ($resource)
    {
      case 'list':
        return $this->actionStatistik($response, $status);
      case 'comment':
    }

    return true;
  }
}
