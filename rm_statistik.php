<?php
/**
 * jBackend rm_statistik plugin for Joomla
 *
 * @author selfget.com (info@selfget.com)
 * @package jBackend
 * @copyright Copyright 2015
 * @license GNU Public License
 * @version 2.0.0
 * @link http://www.selfget.com
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;

class plgJBackendRm_Statistik extends JPlugin
{
  public function __construct(&$subject, $config)
  {
    parent::__construct($subject, $config);
    $this->loadLanguage();
  }

  /**
   * This is the function to generate plugin specific errors
   * 
   * @param string $errorCode The error code to generate
   * @return array The response of type error to return
   */
  private function generateError($errorCode)
  {
    $error = array(
      'status' => 'ko',
      'error_code' => $errorCode
    );
    
    switch($errorCode) {
      case 'REQ_NVAL':
        $error['status_code'] = 405;
        $error['error_description'] = 'Request not supported';
        break;
      case 'REQ_ANS':
        $error['status_code'] = 400;
        $error['error_description'] = 'Action not specified';
        break;
      case 'DB_ERROR':
        $error['status_code'] = 500;
        $error['error_description'] = 'Database error occurred';
        break;
      default:
        $error['status_code'] = 500;
        $error['error_description'] = 'Unknown error';
    }
    
    return $error;
  }

  /**
   * Sanitize table name to prevent SQL injection
   * 
   * @param string $tableName The table name to sanitize
   * @return string The sanitized table name
   */
  private function sanitizeTableName($tableName)
  {
    // Only allow alphanumeric characters and underscores
    return preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
  }

  /**
   * Get total number of routes
   * 
   * @return int Number of routes or 0 on error
   */
  private function getRoutesTotal()
  {
    $app = Factory::getApplication();
    $routes_total = $app->input->get('routes_total', 0, 'BOOL');

    if ($routes_total != 1) {
      return 0;
    }

    try {
      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__act_route'))
        ->where($db->quoteName('state') . ' IN (1, -1)');

      $db->setQuery($query);
      return (int) $db->loadResult();
      
    } catch (Exception $e) {
      Log::add('Error in getRoutesTotal: ' . $e->getMessage(), Log::ERROR, 'plg_jbackend_rm_statistik');
      return 0;
    }
  }

  /**
   * Get total number of comments
   * 
   * @return int Number of comments or 0 on error
   */
  private function getCommentsTotal()
  {
    $app = Factory::getApplication();
    $comments_total = $app->input->get('comments_total', 0, 'BOOL');

    if ($comments_total != 1) {
      return 0;
    }

    try {
      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select('COUNT(' . $db->quoteName('c.id') . ')')
        ->from($db->quoteName('#__act_comment', 'c'))
        ->join('LEFT', $db->quoteName('#__act_route', 'r') . ' ON ' . $db->quoteName('r.id') . ' = ' . $db->quoteName('c.route'))
        ->where($db->quoteName('c.state') . ' IN (1, -1)');

      $db->setQuery($query);
      return (int) $db->loadResult();
      
    } catch (Exception $e) {
      Log::add('Error in getCommentsTotal: ' . $e->getMessage(), Log::ERROR, 'plg_jbackend_rm_statistik');
      return 0;
    }
  }

  /**
   * Get total number of new routes
   * 
   * @return int Number of new routes or 0 on error
   */
  private function getNewRoutesTotal()
  {
    $app = Factory::getApplication();
    $new_routes_total = $app->input->get('new_routes_total', 0, 'UINT');

    if ($new_routes_total != 1) {
      return 0;
    }

    try {
      $params = ComponentHelper::getParams('com_act');
      $newRouteDateRange = (int) $params->get('newroutedaterange', 7);

      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select('COUNT(CASE WHEN ' . $db->quoteName('a.setterdate') . ' > DATE_SUB(NOW(), INTERVAL ' . $newRouteDateRange . ' DAY) THEN 1 ELSE NULL END) AS newroutes')
        ->from($db->quoteName('#__act_route', 'a'))
        ->where($db->quoteName('a.state') . ' IN (1, -1)')
        ->where($db->quoteName('a.hidden') . ' != 1');

      $db->setQuery($query);
      return (int) $db->loadResult();
      
    } catch (Exception $e) {
      Log::add('Error in getNewRoutesTotal: ' . $e->getMessage(), Log::ERROR, 'plg_jbackend_rm_statistik');
      return 0;
    }
  }

  /**
   * Get comments with route information
   * 
   * @return array|null Array of comments or null on error
   */
  private function getComments()
  {
    $app = Factory::getApplication();
    $limit = $app->input->get('limit_comments', 0, 'UINT');

    if ($limit <= 0) {
      return null;
    }

    try {
      $params = ComponentHelper::getParams('com_act');
      $grade_table = $this->sanitizeTableName($params->get('grade_table', 'act_grade'));

      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select([
          $db->quoteName('c.comment'),
          $db->quoteName('c.stars'),
          $db->quoteName('cg.grade', 'c_grade'),
          $db->quoteName('c.route', 'route_id'),
          $db->quoteName('r.name', 'routename'),
          $db->quoteName('vr.grade', 'my_grade')
        ])
        ->from($db->quoteName('#__act_comment', 'c'))
        ->join('LEFT', $db->quoteName('#__act_trigger_calc', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('c.route'))
        ->join('LEFT', $db->quoteName('#__act_route', 'r') . ' ON ' . $db->quoteName('r.id') . ' = ' . $db->quoteName('c.route'))
        ->join('LEFT', $db->quoteName('#__' . $grade_table, 'cg') . ' ON ' . $db->quoteName('cg.id_grade') . ' = ' . $db->quoteName('t.calc_grade_round'))
        ->join('LEFT', $db->quoteName('#__' . $grade_table, 'vr') . ' ON ' . $db->quoteName('vr.id_grade') . ' = ' . $db->quoteName('c.myroutegrade'))
        ->where($db->quoteName('c.state') . ' = 1')
        ->where($db->quoteName('c.comment') . ' != ' . $db->quote(''))
        ->order($db->quoteName('c.created') . ' DESC')
        ->setLimit($limit);

      $db->setQuery($query);
      return $db->loadAssocList();
      
    } catch (Exception $e) {
      Log::add('Error in getComments: ' . $e->getMessage(), Log::ERROR, 'plg_jbackend_rm_statistik');
      return null;
    }
  }

  /**
   * Get routes with all related information
   * 
   * @return array|null Array of routes or null on error
   */
  private function getRoutes()
  {
    $app = Factory::getApplication();
    $limit = $app->input->get('limit_routes', 0, 'UINT');

    if ($limit <= 0) {
      return null;
    }

    try {
      $params = ComponentHelper::getParams('com_act');
      $grade_table = $this->sanitizeTableName($params->get('grade_table', 'act_grade'));

      $db = Factory::getDbo();
      $query = $db->getQuery(true)
        ->select([
          $db->quoteName('r.id'),
          $db->quoteName('r.name'),
          $db->quoteName('s.settername'),
          $db->quoteName('vr.grade', 'settergrade'),
          $db->quoteName('cg.grade', 'c_grade'),
          'IFNULL(ROUND(' . $db->quoteName('t.avg_stars') . ', 1), 0) AS ' . $db->quoteName('rating'),
          $db->quoteName('r.setterdate'),
          $db->quoteName('c.color'),
          $db->quoteName('sec.sector', 'sector_name')
        ])
        ->from($db->quoteName('#__act_route', 'r'))
        ->join('LEFT', $db->quoteName('#__act_setter', 's') . ' ON ' . $db->quoteName('s.id') . ' = ' . $db->quoteName('r.setter'))
        ->join('LEFT', $db->quoteName('#__act_trigger_calc', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('r.id'))
        ->join('LEFT', $db->quoteName('#__' . $grade_table, 'cg') . ' ON ' . $db->quoteName('cg.id_grade') . ' = ' . $db->quoteName('t.calc_grade_round'))
        ->join('LEFT', $db->quoteName('#__' . $grade_table, 'vr') . ' ON ' . $db->quoteName('vr.id_grade') . ' = ' . $db->quoteName('r.settergrade'))
        ->join('LEFT', $db->quoteName('#__act_color', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('r.color'))
        ->join('LEFT', $db->quoteName('#__act_line', 'l') . ' ON ' . $db->quoteName('l.id') . ' = ' . $db->quoteName('r.line'))
        ->join('LEFT', $db->quoteName('#__act_sector', 'sec') . ' ON ' . $db->quoteName('sec.id') . ' = ' . $db->quoteName('l.sector'))
        ->where($db->quoteName('r.state') . ' = 1')
        ->order($db->quoteName('r.setterdate') . ' DESC')
        ->setLimit($limit);

      $db->setQuery($query);
      return $db->loadAssocList();
      
    } catch (Exception $e) {
      Log::add('Error in getRoutes: ' . $e->getMessage(), Log::ERROR, 'plg_jbackend_rm_statistik');
      return null;
    }
  }

  /**
   * Combine all statistics data
   * 
   * @param array $response Response array to populate
   * @param mixed $status Status object
   * @return bool True on success
   */
  private function actionStatistik(&$response, &$status = null)
  {
    try {
      $response['data'] = [
        'route' => $this->getRoutes(),
        'comment' => $this->getComments(),
        'routes_total' => $this->getRoutesTotal(),
        'comments_total' => $this->getCommentsTotal(),
        'new_routes_total' => $this->getNewRoutesTotal()
      ];

      $response['status'] = 'ok';
      return true;
      
    } catch (Exception $e) {
      Log::add('Error in actionStatistik: ' . $e->getMessage(), Log::ERROR, 'plg_jbackend_rm_statistik');
      $response = $this->generateError('DB_ERROR');
      return false;
    }
  }

  /**
   * Fulfills requests for rm_statistik module
   *
   * @param string $module The module invoked
   * @param array $response The response generated
   * @param mixed $status The boundary conditions
   * @return bool True if there are no problems
   */
  public function onRequestRm_Statistik($module, &$response, &$status = null)
  {
    // Check if this is the triggered module
    if ($module !== 'rm_statistik') {
      return true;
    }

    // Add to module call stack
    jBackendHelper::moduleStack($status, 'rm_statistik');

    $app = Factory::getApplication();
    $action = $app->input->getString('action');
    $resource = $app->input->getString('resource');

    // Check if the action is specified
    if (empty($action)) {
      $response = $this->generateError('REQ_ANS');
      return false;
    }

    // Only GET requests are supported
    if ($action !== 'get') {
      $response = $this->generateError('REQ_NVAL');
      return false;
    }

    // Handle resources
    switch ($resource) {
      case 'list':
        return $this->actionStatistik($response, $status);
      
      default:
        // Unknown resource - let jBackend handle it
        return true;
    }
  }
}
