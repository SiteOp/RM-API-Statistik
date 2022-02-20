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
    }
    return $error;
  }


  /**
   * Anzahl Routen
   */
  public static function getRoutesTotal() {
    $app = Factory::getApplication();
    $routes_total    = $app->input->get('routes_total', 0, 'BOOL');

      if(1 == $routes_total) {
        $db = Factory::getDbo();

        $query = $db->getQuery(true);
        $query->select(array('COUNT(*)'))
              ->from('#__act_route')
              ->where('state IN(1,-1)');

        $db->setQuery($query);
        $result = $db->loadResult();
      }
      else {
        $result = 0;
      }
  
    return $result;
  }


  
  /**
   * Anzahl Kommentare
   */
  public static function getCommentsTotal() {

    $app = Factory::getApplication();
    $comments_total    = $app->input->get('comments_total', 0, 'BOOL');

      if(1 == $comments_total) {
        $db = Factory::getDbo();

        $query = $db->getQuery(true);
        $query->select(array('COUNT(c.id)'))
              ->from('#__act_comment AS c')
              ->join('LEFT', '#__act_route AS r ON r.id = c.route')
              ->where('c.state IN(1,-1)');

        $db->setQuery($query);
        $result = $db->loadResult();
      }
      else {
        $result = 0;
      }

    return $result;
  }


  public static function getNewRoutesTotal(){

    $app = Factory::getApplication();
    $new_routes_total    = $app->input->get('new_routes_total', 0, 'UINT');

      if(1 == $new_routes_total) {

        $db = Factory::getDbo();
        $params    = JComponentHelper::getParams('com_act');
        $newRouteDateRange  = $params['newroutedaterange'];

        $query = $db->getQuery(true);
        $query->select('COUNT(CASE WHEN a.setterdate > DATE_SUB( NOW(), INTERVAL '.$newRouteDateRange.' DAY ) then 1 ELSE NULL END) as  newroutes')
              ->from('#__act_route AS a')
              ->where('a.state IN(1,-1)')
              ->where('a.hidden != 1');
              
        $db->setQuery($query);
        $result = $db->loadResult();
      }
        else {
          $result = 0;
      }
      return $result;
  }
  

  /**
   * Kommentare
   */
  public function getComments(){

    $app = Factory::getApplication();
    $limit    = $app->input->get('limit_comments', 0, 'UINT');

      if($limit >0) {

        $db	   = Factory::getDBO();
        $query = $db->getQuery(true);

        $query->select(array('c.comment','c.stars','g.uiaa', 'c.route AS route_id'))
              ->from('#__act_comment AS c')
              ->join('LEFT', '#__act_grade AS g ON g.id = c.myroutegrade')
              ->where('c.state = 1')
              ->where('c.comment != "" ')
              ->order('c.created DESC')
              ->setLimit($limit); 

        $db->setQuery($query);
      
        return $db->loadAssocList();
      }
    return 0;
  }



  /**
   * Routen
   */
  public function getRoutes() {

    $app = Factory::getApplication();
    $limit    = $app->input->get('limit_routes', 0, 'UINT');

      if($limit >0) {
      
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
      else { 
        return 0;
      }
  }

  /**
   * Zusammenfügen
   */
  public function actionStatistik(&$response, &$status = null) {
    $response['data'] = array('route' =>  self::getRoutes(),
                              'comment' => self::getComments(),
                              'routes_total' => self::getRoutesTotal(),
                              'comments_total' => self::getCommentsTotal(),
                              'new_routes_total' => self::getNewRoutesTotal()
                   );

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
