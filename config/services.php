<?php
return ['google_sheets'=>['spreadsheet_id'=>env('GOOGLE_SHEETS_ID'),'credentials'=>env('GOOGLE_APPLICATION_CREDENTIALS','/var/www/html/storage/app/google-service-account.json'),'tab'=>env('GOOGLE_SHEETS_TAB','FinancaPlus_Lancamentos'),'owner_email'=>env('GOOGLE_SHEETS_OWNER_EMAIL'),'sync_enabled'=>filter_var(env('GOOGLE_SHEETS_SYNC_ENABLED',false),FILTER_VALIDATE_BOOL)]];
