php artisan tinker --execute="
\$user = \App\Models\User::first();
\$req = \Illuminate\Http\Request::create('/api/v1/mail?folder=all', 'GET');
\$req->setUserResolver(function() use (\$user) { return \$user; });
\$controller = new \Modules\MailBox\Http\Controllers\MailBoxController();
\$resp = \$controller->index(\$req);
echo json_encode(\$resp->getData(true)['data'] ?? []);
"
