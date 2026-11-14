import paramiko
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('103.67.197.240', username='root', password='kR149UmVUHY7eD2B')
cmd = "mysql -u root tiemai_shop -e 'SELECT id, order_code, product_id, customer_email, delivered_data, created_at, status FROM orders WHERE order_code = \"VK982708\";'"
stdin, stdout, stderr = ssh.exec_command(cmd)
print("OUT:", stdout.read().decode())
ssh.close()
