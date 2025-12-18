            // Generate ID for purchase request from sequences
            $getId = $pdo->prepare("SELECT last_no FROM sequences WHERE name = 'purchaserequest' FOR UPDATE");
            $getId->execute();
            $lastNo = $getId->fetchColumn();
            $newNo = $lastNo + 1;
            $idrequest = 'PR-' . str_pad($newNo, 4, '0', STR_PAD_LEFT);
            
            // Update sequence
            $updateSeq = $pdo->prepare("UPDATE sequences SET last_no = ? WHERE name = 'purchaserequest'");
            $updateSeq->execute([$newNo]);
            
            // Insert purchase request
            $insertPR = $pdo->prepare("INSERT INTO purchaserequest (idrequest, iduserrequest, tgl_req, namarequestor, keterangan, tgl_butuh, idsupervisor) VALUES (?, ?, ?, ?, ?, ?, ?)");
            error_log('Executing purchase request insert query with idrequest: ' . $idrequest);
            if ($insertPR->execute([$idrequest, $iduserrequest, $tgl_req, $namarequestor, $keterangan, $tgl_butuh, $idsupervisor])) {
                error_log('Purchase request insert successful');
                
                // Insert detail request if any detail fields are provided
                if (!empty($idbarang) || !empty($namaitem)) {
                    error_log('Inserting detail request');
                    $insertDetail = $pdo->prepare("INSERT INTO detailrequest (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject, kodebarang, satuan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $insertDetail->execute([$idbarang, $idrequest, $linkpembelian, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $kodebarang, $satuan]);
                    error_log('Detail request insert result: ' . ($result ? 'success' : 'failed'));
                }
                
                // Log initial status
                error_log('Inserting log status');
                $logStatus = $pdo->prepare("INSERT INTO logstatusreq (idrequest, status, date, userid) VALUES (?, ?, NOW(), ?)");
                $result = $logStatus->execute([$idrequest, $initialStatus, $iduserrequest]);
                error_log('Log status insert result: ' . ($result ? 'success' : 'failed'));