@echo off
echo Applying PR ID Generation Changes to procurement.php
echo ==================================================

echo Step 1: Adding generatePRId function to procurement.php
echo Adding function after session_start()...

echo.
echo Please add the following function to procurement.php, right after the session_start() block:
echo.
echo /**                                                                  
echo  * Function to generate PR ID                                        
echo  * @param PDO %pdo Database connection                               
echo  * @return string Generated PR ID in format PRYYYYNNNN              
echo  */                                                                 
echo function generatePRId(PDO %pdo) {                                   
echo     %year = date('Y');                                              
echo.                                                                    
echo     %stmt = %pdo-^>prepare("                                         
echo         SELECT idrequest                                            
echo         FROM purchaserequest                                        
echo         WHERE idrequest LIKE :prefix                                 
echo         ORDER BY idrequest DESC                                     
echo         LIMIT 1                                                     
echo     ");                                                             
echo     %stmt-^>execute([':prefix' =^> "PR{%year}%%"]);                 
echo     %lastId = %stmt-^>fetchColumn();                                
echo.                                                                    
echo     if (%lastId) {                                                  
echo         %lastNumber = (int) substr(%lastId, -4);                    
echo         %newNumber = %lastNumber + 1;                              
echo     } else {                                                        
echo         %newNumber = 1;                                             
echo     }                                                               
echo.                                                                    
echo     return 'PR' . %year . str_pad(%newNumber, 4, '0', STR_PAD_LEFT);
echo }                                                                   
echo.

echo Step 2: Replace ID generation code
echo.
echo In the add purchase request handler section (around lines 71-80), replace:
echo ================= OLD CODE =================
echo // Generate ID for purchaserequest using sequences
echo %stmt = %pdo-^>prepare("SELECT last_no FROM sequences WHERE name = 'purchaserequest'");
echo %stmt-^>execute();
echo %sequence = %stmt-^>fetch(PDO::FETCH_ASSOC);
echo %lastNo = %sequence ? %sequence['last_no'] + 1 : 1;
echo %idrequest = 'PR-' . str_pad(%lastNo, 6, '0', STR_PAD_LEFT);
echo.
echo // Update the sequence
echo %stmt = %pdo-^>prepare("INSERT INTO sequences (name, last_no) VALUES ('purchaserequest', ?) ON DUPLICATE KEY UPDATE last_no = ?");
echo %stmt-^>execute([%lastNo, %lastNo]);
echo ================= OLD CODE =================
echo.
echo With:
echo ================= NEW CODE =================
echo // Generate ID for purchaserequest using the generatePRId function
echo %idrequest = generatePRId(%pdo);
echo ================= NEW CODE =================
echo.
echo Changes completed! Please apply these changes manually to procurement.php
pause