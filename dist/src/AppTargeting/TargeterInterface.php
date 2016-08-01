<?php


namespace Curator\AppTargeting;


interface TargeterInterface {
  function getAppName();
  function getCurrentVersion();
  function getVariantTags();
}
