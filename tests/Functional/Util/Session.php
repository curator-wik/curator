<?php


namespace Curator\Tests\Functional\Util;


use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Session {
  public static function makeSessionAuthenticated(SessionInterface $session) {
    $session->set('IsAuthenticated', true);
    $session->save();
  }

  public static function makeSessionUnauthenticated(SessionInterface $session) {
    $session->set('IsAuthenticated', false);
    $session->save();
  }
}
