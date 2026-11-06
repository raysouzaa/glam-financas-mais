<?php
return [
 'default'=>env('DB_CONNECTION','pgsql'),
 'connections'=>[
  'pgsql'=>['driver'=>'pgsql','url'=>env('DB_URL'),'host'=>env('DB_HOST','db'),'port'=>env('DB_PORT','5432'),'database'=>env('DB_DATABASE','financaplus'),'username'=>env('DB_USERNAME','financaplus'),'password'=>env('DB_PASSWORD',''),'charset'=>'utf8','prefix'=>'','prefix_indexes'=>true,'search_path'=>'public','sslmode'=>'prefer'],
  'sqlite'=>['driver'=>'sqlite','database'=>env('DB_DATABASE',database_path('database.sqlite')),'prefix'=>'','foreign_key_constraints'=>true],
 ],
 'migrations'=>['table'=>'migrations'],
];
