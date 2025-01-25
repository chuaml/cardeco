@REM stop and remove all existing deployed container stacks
for /f "tokens=*" %i in ('docker stack ls --format "{{.Name}}"') do docker stack rm %i
