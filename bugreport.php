<?php
  /**
   * bugreport.php
   *
   * part of
   * NWWIMP - NitricWare's Where Is My Punsch
   *
   * by NitricWare/Kurt Höblinger
   *
   * MIT License
   */

  /**
   * Date string representing now
   * @var string
   */

  $now = date('d.m.y H:i');

  if (!file_exists('bugreports.txt')){
    file_put_contents('bugreports.txt', "created $now");
  }

  /**
   * Caching the previous contents of the bugreport txt file
   * @var string
   */

  $previous = file_get_contents('bugreports.txt');

  file_put_contents('bugreports.txt', "$previous\n$now - lat:".$_GET['latitude']." long:".$_GET['longitude']);
