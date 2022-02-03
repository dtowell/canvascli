FOR /F "usebackq tokens=*" %%a IN (`cd`) DO @wsl --cd %%a ./photos.sh
pause