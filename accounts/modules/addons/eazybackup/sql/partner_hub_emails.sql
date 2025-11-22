INSERT INTO tblemailtemplates (`type`,`name`,`subject`,`message`,`plaintext`,`language`,`disabled`)
SELECT 'general','eB Partner Hub — Customer Welcome','Welcome to {$Brand_ProductName}','<p>Hi {$client_name},</p>
<p>Welcome to {$Brand_ProductName}! Your backup account is ready.</p>
<p><strong>Username:</strong> {$Account_Username}</p>
<p>You can download the backup client for your platform here:</p>
<p><a href="{$Downloads_Url}">{$Downloads_Url}</a></p>
<p>Server Address: {$Brand_ServerAddress}</p>
<p>If you have any questions, just reply to this email.</p>
','0','',0
WHERE NOT EXISTS (SELECT 1 FROM tblemailtemplates WHERE name='eB Partner Hub — Customer Welcome');

INSERT INTO tblemailtemplates (`type`,`name`,`subject`,`message`,`plaintext`,`language`,`disabled`)
SELECT 'general','eB Partner Hub — MSP New Order Notice','New signup: {$Customer_Email}','<p>A new signup was received.</p>
<p><strong>Customer:</strong> {$Customer_Name} ({$Customer_Email})</p>
<p><strong>Username:</strong> {$Account_Username}</p>
<p><strong>Downloads:</strong> <a href="{$Downloads_Url}">{$Downloads_Url}</a></p>
<p><strong>Product:</strong> {$Brand_ProductName}</p>
','0','',0
WHERE NOT EXISTS (SELECT 1 FROM tblemailtemplates WHERE name='eB Partner Hub — MSP New Order Notice');


