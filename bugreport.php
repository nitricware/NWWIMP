<?php
  $now = date('d.m.y H:i');
  if (!file_exists('bugreports.txt')){
    file_put_contents('bugreports.txt', "created $now");
  }

  $previous = file_get_contents('bugreports.txt');
  file_put_contents('bugreports.txt', "$previous\n$now - lat:".$_GET['latitude']." long:".$_GET['longitude']);
