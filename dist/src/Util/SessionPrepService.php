<?php


namespace Curator\Util;

/**
 * Class SessionPrepService
 *   Ensures the PHP runtime environment is ready for a new native session.
 *
 *   Because Curator may be started as part of the same request in which the
 *   adjoining application has been already running, PHP's internal session
 *   handling machinery is likely to be in a state incompatible with starting
 *   another new session. We use the NativeSession/NativeSessionStorage symfony
 *   components, so the purpose of this service is to ensure PHP session
 *   machinery is returned to the pristine state they would be in at the
 *   beginning of a request.
 */
class SessionPrepService {
  public function prepareForNewSession() {
    // The server default handler needs to be in effect for Curator's session.
    session_set_save_handler(new \SessionHandler());

    $this->ensureSessionId();
  }

  protected function ensureSessionId($retry = false) {
    // If a session ID is already registered, as is likely the case from
    // activity by the adjoining application, we need to choose a different one.
    // This is both for security and as a necessity, because the set of allowed
    // characters in session ids varies by session save handler and so the
    // current ID may result in a failure to session_start() the native handler.
    // It isn't possible to "reset" the session id to a falsy value that causes
    // the native session handler to generate one.
    if (strlen(session_id()) > 0) {
      if (function_exists('session_create_id')) {
        $new_id = session_create_id();
      }
      else {
        $new_id = $this->generateSessionId();
      }

      session_id($new_id);
      // There's a ridiculously small chance that this could actually start
      // a "new" session on someone's existing session. Since this code runs
      // infrequently relative to all incoming requests, let's err on the side of
      // caution and verify it's really an unused session id.
      $useCookies = ini_get('session.use_cookies');
      ini_set('session.use_cookies', '0');
      session_start();
      $isCollision = count($_SESSION) > 0;
      session_write_close();
      ini_set('session.use_cookies', $useCookies);

      if ($isCollision) {
        if ($retry === TRUE) {
          throw new \RuntimeException('Unable to generate a new non-conflicting session id.');
        }
        $this->ensureSessionId(TRUE);
      }
    }
  }

  protected function generateSessionId() {
    return bin2hex(random_bytes(16));
  }
}
