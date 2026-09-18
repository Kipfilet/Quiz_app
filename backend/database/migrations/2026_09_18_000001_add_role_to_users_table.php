<?php

return [
    'up' => "ALTER TABLE users ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER password_hash;",
    'down' => "ALTER TABLE users DROP COLUMN role;",
];
