                                            <th>Kode Barang</th>
                                            <th>Nama Barang</th>
                                            <th>Kategori</th>
                                            <th>Jumlah</th>
                                            <th>Harga</th>
                                            <th>Total</th>
                                            <th>Dibuat Oleh</th>
                                            <th width="100">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($items)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center">Tidak ada data detail keluar</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($items as $index => $item): 
                                                $row_number = $offset + $index + 1;
                                            ?>
                                            <tr>
                                                <td><?php echo $row_number; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($item['tgl_keluar'])); ?></td>
                                                <td><?php echo htmlspecialchars($item['kodebarang'] ?? $item['idbarang'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['nama_barang'] ?? $item['item_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></td>
                                                <td><?php echo number_format($item['qty']); ?></td>
                                                <td>Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                                <td>Rp <?php echo number_format($item['total'], 0, ',', '.'); ?></td>
                                                <td><?php echo htmlspecialchars($item['created_by']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="#" class="btn btn-sm btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" style="margin-top: 20px;">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++): 
                                    ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
    
    <script>
        // Toggle sidebar
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.app-sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
        
        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        
        // Initialize active tab
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = '<?php echo $activeTab; ?>';
            if (activeTab) {
                switchTab(activeTab);
            }
        });
    </script>
</body>
</html>