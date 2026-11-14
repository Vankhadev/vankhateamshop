import paramiko
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('103.67.197.240', username='root', password='kR149UmVUHY7eD2B')
cmd = "mysql -u root tiemai_shop -e 'SELECT id, name, sku, duration_days, request_count, description, price FROM products WHERE name LIKE \"%Cursor%\";'"
stdin, stdout, stderr = ssh.exec_command(cmd)
print("OUT:", stdout.read().decode())
ssh.close()
