<?php
foreach (\App\Models\User::select('name','email','role')->orderBy('role')->get() as $u) {
    echo $u->email . ' | ' . $u->role . ' | ' . $u->name . PHP_EOL;
}
