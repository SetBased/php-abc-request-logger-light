<?php
declare(strict_types=1);

namespace Plaisio\RequestLogger;

use Plaisio\C;
use Plaisio\Kernel\Nub;

/**
 * A HTTP page request logger for light and development websites.
 */
class RequestLoggerLight implements RequestLogger
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * If true the HTTP page request details (i.e. cookies, post variables and queries) must be logged.
   *
   * @var bool
   *
   * @api
   * @since 1.0.0
   */
  public $logRequestDetails;

  /**
   * If true the HTTP page request must be logged.
   *
   * @var bool
   *
   * @api
   * @since 1.0.0
   */
  public $logRequests;

  /**
   * The ID of the logged page request.
   *
   * @var int|null
   *
   * @api
   * @since 1.0.0
   */
  public $rqlId;

  //--------------------------------------------------------------------------------------------------------------------

  /**
   * Object constructor.
   */
  public function __construct()
  {
    $this->logRequests       = true;
    $this->logRequestDetails = false;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logs the HTTP page request.
   *
   * @param int|null $status The HTTP status code.
   *
   * @api
   * @since 1.0.0
   */
  public function logRequest(?int $status): void
  {
    if ($this->logRequests)
    {
      $this->rqlId = Nub::$DL->abcRequestLoggerLightInsertRequest(
        Nub::$session->getSesId(),
        Nub::$companyResolver->getCmpId(),
        Nub::$session->getUsrId(),
        Nub::$requestHandler->getPagId(),
        mb_substr(Nub::$request->getRequestUri() ?? '', 0, C::LEN_RQL_REQUEST),
        mb_substr(Nub::$request->getMethod() ?? '', 0, C::LEN_RQL_METHOD),
        mb_substr($_SERVER['HTTP_REFERER'] ?? '', 0, C::LEN_RQL_REFERRER),
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, C::LEN_RQL_ACCEPT_LANGUAGE),
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, C::LEN_RQL_USER_AGENT),
        $status,
        count(Nub::$DL->getQueryLog()),
        (Nub::$time0!==null) ? microtime(true) - Nub::$time0 : null);

      if ($this->logRequestDetails)
      {
        $oldLogQueries        = Nub::$DL->logQueries;
        Nub::$DL->logQueries = false;

        $this->requestLogQuery();
        $this->requestLogPost($_POST);
        $this->requestLogCookie($_COOKIE);

        Nub::$DL->logQueries = $oldLogQueries;
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logs the (by the user agent) sent cookies into the database.
   *
   * Usage on this method on production environments is not recommended.
   *
   * @param array       $cookies    must be $_COOKIES
   * @param string|null $parentName must not be used, intended for use by recursive calls only.
   */
  private function requestLogCookie(array $cookies, ?string $parentName = null): void
  {
    if (is_array($cookies))
    {
      foreach ($cookies as $name => $value)
      {
        $fullName = ($parentName===null) ? $name : $parentName.'['.$name.']';

        if (is_array($value))
        {
          $this->requestLogCookie($value, $fullName);
        }
        else
        {
          Nub::$DL->abcRequestLoggerLightInsertCookie($this->rqlId, $fullName, $value);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logs the post variables into the database.
   *
   * Usage on this method on production environments is not recommended.
   *
   * @param array       $post       Must be $_POST (except for recursive calls).
   * @param string|null $parentName Must not be used (except for recursive calls).
   */
  private function requestLogPost(array $post, ?string $parentName = null): void
  {
    if (is_array($post))
    {
      foreach ($post as $name => $value)
      {
        $fullName = ($parentName===null) ? $name : $parentName.'['.$name.']';

        if (is_array($value))
        {
          $this->requestLogPost($value, $fullName);
        }
        else
        {
          // Don't log passwords.
          if (is_string($name) && strpos($name, 'password')!==false)
          {
            $value = str_repeat('*', mb_strlen($name));
          }

          Nub::$DL->abcRequestLoggerLightInsertPost($this->rqlId, $fullName, $value);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Logs the executed SQL queries into the database.
   */
  private function requestLogQuery(): void
  {
    $queries = Nub::$DL->getQueryLog();

    foreach ($queries as $query)
    {
      Nub::$DL->abcRequestLoggerLightInsertQuery($this->rqlId, $query['query'], $query['time']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
