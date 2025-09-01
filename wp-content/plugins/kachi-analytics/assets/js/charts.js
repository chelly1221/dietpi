// Statistics Dashboard Charts JavaScript
(function($) {
    'use strict';
    
    // Charts module
    window.sdCharts = {
        // Render uploads timeline chart
        renderUploadsChart: function(data) {
            const ctx = document.getElementById('chart-uploads-timeline');
            if (!ctx) return;
            
            if (!data || !data.dates || data.dates.length === 0) {
                const $container = $(ctx).closest('.sd-uploads-chart-card');
                if (!$container.find('.sd-no-data').length) {
                    if (window.sdState.charts.uploads) {
                        window.sdState.charts.uploads.destroy();
                        window.sdState.charts.uploads = null;
                    }
                    $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
                }
                $('#total-uploads').text('0');
                $('#avg-uploads').text('0');
                return;
            }
            
            // Update stats
            $('#total-uploads').text(window.sdUtils.formatNumber(data.total || 0));
            const avgUploads = data.dates.length > 0 ? (data.total || 0) / data.dates.length : 0;
            $('#avg-uploads').text(avgUploads.toFixed(1));
            
            // Check if data has changed
            const currentData = window.sdState.charts.uploads?.data?.datasets?.[0]?.data;
            const dataChanged = !currentData || JSON.stringify(currentData) !== JSON.stringify(data.counts);
            
            if (window.sdState.charts.uploads && !dataChanged) {
                return;
            }
            
            if (window.sdState.charts.uploads) {
                window.sdState.charts.uploads.destroy();
            }
            
            window.sdState.charts.uploads = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.dates || [],
                    datasets: [{
                        label: '업로드 수',
                        data: data.counts || [],
                        borderColor: window.sdConfig.chartColors.primary,
                        backgroundColor: window.sdConfig.chartColors.background,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: window.sdConfig.chartColors.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: dataChanged ? 750 : 0
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(tooltipItems) {
                                    return window.sdUtils.formatDate(tooltipItems[0].label);
                                },
                                label: function(context) {
                                    return '업로드: ' + context.parsed.y + '개';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: window.sdConfig.chartColors.grid,
                                borderColor: window.sdConfig.chartColors.grid
                            },
                            ticks: {
                                color: window.sdConfig.chartColors.text,
                                maxRotation: 45,
                                minRotation: 45,
                                callback: function(value, index) {
                                    const total = this.chart.data.labels.length;
                                    const interval = Math.ceil(total / 10);
                                    return index % interval === 0 ? 
                                        window.sdUtils.formatDate(this.chart.data.labels[index], true) : '';
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: window.sdConfig.chartColors.grid,
                                borderColor: window.sdConfig.chartColors.grid
                            },
                            ticks: {
                                color: window.sdConfig.chartColors.text,
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        },
        
        // Render document types chart
        renderDocumentTypesChart: function(data) {
            const ctx = document.getElementById('chart-document-types');
            if (!ctx) return;
            
            const labels = Object.keys(data || {});
            const values = Object.values(data || {});
            
            if (labels.length === 0) {
                const $container = $(ctx).closest('.sd-chart-container');
                if (!$container.find('.sd-no-data').length) {
                    if (window.sdState.charts.documentTypes) {
                        window.sdState.charts.documentTypes.destroy();
                        window.sdState.charts.documentTypes = null;
                    }
                    $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
                }
                return;
            }
            
            const currentData = window.sdState.charts.documentTypes?.data?.datasets?.[0]?.data;
            const dataChanged = !currentData || JSON.stringify(currentData) !== JSON.stringify(values);
            
            if (window.sdState.charts.documentTypes && !dataChanged) {
                return;
            }
            
            if (window.sdState.charts.documentTypes) {
                window.sdState.charts.documentTypes.destroy();
            }
            
            window.sdState.charts.documentTypes = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels.map(label => label.toUpperCase()),
                    datasets: [{
                        data: values,
                        backgroundColor: window.sdUtils.generateColors(labels.length),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: dataChanged ? 750 : 0
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: window.sdConfig.chartColors.text,
                                padding: 16,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        // Render horizontal bar chart
        renderHorizontalBarChart: function(chartId, data, color, stateKey) {
            const ctx = document.getElementById(chartId);
            if (!ctx) return;
            
            const labels = Object.keys(data || {});
            const values = Object.values(data || {});
            
            if (labels.length === 0) {
                const $container = $(ctx).closest('.sd-chart-container');
                if (!$container.find('.sd-no-data').length) {
                    if (window.sdState.charts[stateKey]) {
                        window.sdState.charts[stateKey].destroy();
                        window.sdState.charts[stateKey] = null;
                    }
                    $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
                }
                return;
            }
            
            // Sort by value and take top 10
            const sorted = labels.map((label, i) => ({ label, value: values[i] }))
                .sort((a, b) => b.value - a.value)
                .slice(0, 10);
            
            const currentData = window.sdState.charts[stateKey]?.data?.datasets?.[0]?.data;
            const sortedValues = sorted.map(item => item.value);
            const dataChanged = !currentData || JSON.stringify(currentData) !== JSON.stringify(sortedValues);
            
            if (window.sdState.charts[stateKey] && !dataChanged) {
                return;
            }
            
            if (window.sdState.charts[stateKey]) {
                window.sdState.charts[stateKey].destroy();
            }
            
            window.sdState.charts[stateKey] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: sorted.map(item => item.label),
                    datasets: [{
                        label: '문서 수',
                        data: sorted.map(item => item.value),
                        backgroundColor: color,
                        borderWidth: 0
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: dataChanged ? 750 : 0
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: window.sdConfig.chartColors.grid,
                                borderColor: window.sdConfig.chartColors.grid
                            },
                            ticks: {
                                color: window.sdConfig.chartColors.text,
                                stepSize: 1
                            }
                        },
                        y: {
                            grid: {
                                display: false,
                                borderColor: window.sdConfig.chartColors.grid
                            },
                            ticks: {
                                color: window.sdConfig.chartColors.text
                            }
                        }
                    }
                }
            });
        },
        
        // Render sosok chart
        renderSosokChart: function(data) {
            this.renderHorizontalBarChart(
                'chart-by-sosok', 
                data, 
                window.sdConfig.chartColors.primary, 
                'sosok'
            );
        },
        
        // Render buseo chart
        renderBuseoChart: function(data) {
            this.renderHorizontalBarChart(
                'chart-by-buseo', 
                data, 
                window.sdConfig.chartColors.secondary, 
                'buseo'
            );
        },
        
        // Render site chart
        renderSiteChart: function(data) {
            this.renderHorizontalBarChart(
                'chart-by-site', 
                data, 
                window.sdConfig.chartColors.tertiary, 
                'site'
            );
        }
    };
    
    // Render module
    window.sdRender = {
        // Render statistics
        renderStatistics: function(data) {
            if (!data) {
                console.error('No statistics data to render');
                return;
            }
            
            // Update stat cards
            $('#total-documents').text(window.sdUtils.formatNumber(data.total_documents || 0));
            $('#total-sections').text(window.sdUtils.formatNumber(data.total_sections || 0));
            $('#avg-sections').text((data.average_sections_per_document || 0).toFixed(1));
            $('#total-sosok').text(Object.keys(data.documents_by_sosok || {}).length);
            
            // Calculate documents by buseo from organization data
            const documentsByBuseo = this.calculateDocumentsByBuseo(data);
            
            // Render charts
            window.sdCharts.renderDocumentTypesChart(data.documents_by_type || {});
            window.sdCharts.renderSosokChart(data.documents_by_sosok || {});
            window.sdCharts.renderBuseoChart(documentsByBuseo);
            window.sdCharts.renderSiteChart(data.documents_by_site || {});
            
            // Render other components
            this.renderPopularTags(data.popular_tags || []);
            this.renderRecentUploads(data.recent_uploads || []);
        },
        
        // Calculate documents by buseo based on site data
        calculateDocumentsByBuseo: function(data) {
            const documentsByBuseo = {};
            const documentsBySite = data.documents_by_site || {};
            const orgData = window.sdState.data.organizationData || {};
            
            // Iterate through all sites and their document counts
            Object.entries(documentsBySite).forEach(([site, count]) => {
                // Find which buseo this site belongs to
                let foundBuseo = null;
                
                // Check if it's a department-wide site (ends with _전체)
                if (site.endsWith('_전체')) {
                    foundBuseo = site.replace('_전체', '');
                } else {
                    // Search through organization data to find the buseo
                    Object.entries(orgData).forEach(([sosok, buseoData]) => {
                        Object.entries(buseoData).forEach(([buseo, sites]) => {
                            if (sites.includes(site)) {
                                foundBuseo = buseo;
                            }
                        });
                    });
                }
                
                // If we found a buseo, add the count
                if (foundBuseo) {
                    if (!documentsByBuseo[foundBuseo]) {
                        documentsByBuseo[foundBuseo] = 0;
                    }
                    documentsByBuseo[foundBuseo] += count;
                } else {
                    // If no buseo found, put it under "기타"
                    if (!documentsByBuseo['기타']) {
                        documentsByBuseo['기타'] = 0;
                    }
                    documentsByBuseo['기타'] += count;
                }
            });
            
            return documentsByBuseo;
        },
        
        // Render popular tags
        renderPopularTags: function(tags) {
            const $container = $('#popular-tags');
            $container.empty();
            
            if (!tags || tags.length === 0) {
                $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
                return;
            }
            
            tags.forEach(tag => {
                const $tag = $(`
                    <div class="sd-tag">
                        <span class="sd-tag-name">${window.sdUtils.escapeHtml(tag.name)}</span>
                        <span class="sd-tag-count">${tag.count}</span>
                    </div>
                `);
                $container.append($tag);
            });
        },
        
        // Render recent uploads
        renderRecentUploads: function(uploads) {
            const $container = $('#recent-uploads');
            $container.empty();
            
            if (!uploads || uploads.length === 0) {
                $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
                return;
            }
            
            uploads.forEach(upload => {
                const $item = $(`
                    <div class="sd-upload-item">
                        <div class="sd-upload-info">
                            <div class="sd-upload-filename">${window.sdUtils.escapeHtml(upload.filename || 'Unknown')}</div>
                            <div class="sd-upload-meta">
                                ${window.sdUtils.escapeHtml(upload.sosok || '-')} / ${window.sdUtils.escapeHtml(upload.site || '-')}
                                ${upload.tags ? ' • ' + window.sdUtils.escapeHtml(upload.tags) : ''}
                            </div>
                        </div>
                        <div class="sd-upload-date">${window.sdUtils.formatDate(upload.upload_date)}</div>
                    </div>
                `);
                $container.append($item);
            });
        },
        
        // Render storage statistics
        renderStorageStats: function(data) {
            const $container = $('#storage-stats');
            $container.empty();
            
            if (!data) {
                $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
                return;
            }
            
            console.log('Storage stats data:', data);
            
            // Check if access is restricted
            if (data.access_level === 'restricted') {
                $container.html(`
                    <div class="sd-access-restricted">
                        <h3>접근 제한</h3>
                        <p>${window.sdUtils.escapeHtml(data.message || '저장소 통계는 관리자만 볼 수 있습니다.')}</p>
                    </div>
                `);
                return;
            }
            
            // Check for error state
            if (data.access_level === 'error') {
                $container.html(`
                    <div class="sd-access-restricted">
                        <h3>오류</h3>
                        <p>${window.sdUtils.escapeHtml(data.message || '저장소 통계를 불러올 수 없습니다.')}</p>
                    </div>
                `);
                return;
            }
            
            // If we have actual data, render it
            if (data.total_size !== undefined || data.file_count !== undefined) {
                // Create storage cards
                const cards = [
                    {
                        title: '전체 저장 용량',
                        stats: [
                            { label: '총 용량', value: window.sdUtils.formatFileSize(data.total_size || 0) },
                            { label: 'GB 단위', value: (data.total_size_gb || 0).toFixed(2) + ' GB' },
                            { label: '파일 수', value: window.sdUtils.formatNumber(data.file_count || 0) + '개' }
                        ]
                    },
                    {
                        title: '파일 정보',
                        stats: [
                            { label: '평균 파일 크기', value: window.sdUtils.formatFileSize(data.average_file_size || 0) },
                            { label: '총 파일 수', value: window.sdUtils.formatNumber(data.file_count || 0) + '개' }
                        ]
                    }
                ];
                
                // Add file type breakdown if available
                if (data.size_by_type_mb) {
                    const typeStats = Object.entries(data.size_by_type_mb)
                        .sort((a, b) => b[1] - a[1])
                        .map(([type, size]) => ({
                            label: type.toUpperCase(),
                            value: size.toFixed(2) + ' MB'
                        }));
                    
                    if (typeStats.length > 0) {
                        cards.push({
                            title: '파일 유형별 용량',
                            stats: typeStats.slice(0, 5)
                        });
                    }
                }
                
                // Render cards
                cards.forEach(card => {
                    const $card = $(`
                        <div class="sd-storage-card">
                            <h3>${window.sdUtils.escapeHtml(card.title)}</h3>
                            ${card.stats.map(stat => `
                                <div class="sd-storage-stat">
                                    <span class="sd-storage-label">${window.sdUtils.escapeHtml(stat.label)}</span>
                                    <span class="sd-storage-value">${window.sdUtils.escapeHtml(stat.value)}</span>
                                </div>
                            `).join('')}
                        </div>
                    `);
                    $container.append($card);
                });
            } else {
                $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
            }
        },
        
        // Render server statistics
        renderServerStats: function(data) {
            const $container = $('#servers-stats');
            $container.empty();
            
            if (!data) {
                $container.html('<p class="sd-no-data">데이터가 없습니다</p>');
                return;
            }
            
            console.log('Server stats data:', data);
            
            // Check if access is restricted
            if (data.access_level === 'restricted') {
                $container.html(`
                    <div class="sd-access-restricted">
                        <h3>접근 제한</h3>
                        <p>${window.sdUtils.escapeHtml(data.message || '서버 통계는 관리자만 볼 수 있습니다.')}</p>
                    </div>
                `);
                return;
            }
            
            // Check for error state
            if (data.access_level === 'error') {
                $container.html(`
                    <div class="sd-access-restricted">
                        <h3>오류</h3>
                        <p>${window.sdUtils.escapeHtml(data.message || '서버 통계를 불러올 수 없습니다.')}</p>
                    </div>
                `);
                return;
            }
            
            // Render AI Server stats
            if (data.ai_server) {
                const aiServer = data.ai_server;
                
                // Parse GPU information from external API if available
                let gpuExternalHtml = '';
                if (aiServer.gpu_external && aiServer.gpu_external.gpus) {
                    gpuExternalHtml = `
                        <div class="sd-server-gpu">
                            <h4>LLM GPU</h4>
                            <div class="sd-gpu-stats">
                                ${aiServer.gpu_external.gpus.map(gpu => {
                                    // Parse memory values
                                    const memTotal = parseInt(gpu.memory.total);
                                    const memUsed = parseInt(gpu.memory.used);
                                    const memPercent = memTotal > 0 ? ((memUsed / memTotal) * 100).toFixed(1) : 0;
                                    
                                    // Parse utilization values
                                    const gpuUtil = parseInt(gpu.utilization.gpu) || 0;
                                    const memUtil = parseInt(gpu.utilization.memory) || 0;
                                    
                                    // Parse temperature
                                    const temperature = parseInt(gpu.temperature) || null;
                                    
                                    // Parse power values
                                    const powerDraw = gpu.power.draw !== 'N/A' ? parseFloat(gpu.power.draw) : null;
                                    const powerLimit = gpu.power.limit !== 'N/A' ? parseFloat(gpu.power.limit) : null;
                                    
                                    return `
                                        <div class="sd-gpu-device">
                                            <h5>${window.sdUtils.escapeHtml(gpu.name)} (GPU ${gpu.id.split(':').pop()})</h5>
                                            
                                            <div class="sd-metric">
                                                <div class="sd-metric-label">GPU 사용률</div>
                                                <div class="sd-progress-bar">
                                                    <div class="sd-progress-fill" style="width: ${gpuUtil}%"></div>
                                                </div>
                                                <div class="sd-metric-info">
                                                    <span>${gpuUtil}%</span>
                                                    ${temperature !== null ? `<span>온도: ${temperature}°C</span>` : ''}
                                                </div>
                                            </div>
                                            
                                            <div class="sd-metric">
                                                <div class="sd-metric-label">메모리</div>
                                                <div class="sd-progress-bar">
                                                    <div class="sd-progress-fill" style="width: ${memPercent}%"></div>
                                                </div>
                                                <div class="sd-metric-info">
                                                    <span>${memPercent}%</span>
                                                    <span>${memUsed} / ${memTotal} MiB</span>
                                                </div>
                                            </div>
                                            
                                            ${powerDraw !== null && powerLimit !== null ? `
                                                <div class="sd-gpu-power">
                                                    <span class="sd-info-label">전력:</span>
                                                    <span class="sd-info-value">${powerDraw}W / ${powerLimit}W</span>
                                                </div>
                                            ` : ''}
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                }
                
                const $aiServerCard = $(`
                    <div class="sd-server-card">
                        <div class="sd-server-header">
                            <h3>
                                <span class="sd-server-icon">🤖</span>
                                ${window.sdUtils.escapeHtml(aiServer.name)}
                            </h3>
                            <span class="sd-server-status sd-status-${aiServer.status}">
                                ${aiServer.status === 'online' ? '🟢 온라인' : '🔴 오프라인'}
                            </span>
                        </div>
                        
                        <div class="sd-server-info">
                            <div class="sd-info-row">
                                <span class="sd-info-label">호스트명:</span>
                                <span class="sd-info-value">${window.sdUtils.escapeHtml(aiServer.hostname)}</span>
                            </div>
                            <div class="sd-info-row">
                                <span class="sd-info-label">IP 주소:</span>
                                <span class="sd-info-value">${window.sdUtils.escapeHtml(aiServer.ip_address)}</span>
                            </div>
                            <div class="sd-info-row">
                                <span class="sd-info-label">운영체제:</span>
                                <span class="sd-info-value">Ubuntu 22.04.5 LTS</span>
                            </div>
                            <div class="sd-info-row">
                                <span class="sd-info-label">Docker Base:</span>
                                <span class="sd-info-value">${window.sdUtils.escapeHtml(aiServer.platform)} (Python ${window.sdUtils.escapeHtml(aiServer.python_version)})</span>
                            </div>
                            <div class="sd-info-row">
                                <span class="sd-info-label">가동 시간:</span>
                                <span class="sd-info-value">${window.sdUtils.formatUptime(aiServer.uptime)}</span>
                            </div>
                        </div>
                        
                        <div class="sd-server-metrics">
                            <div class="sd-metric">
                                <h4>CPU</h4>
                                <div class="sd-progress-bar">
                                    <div class="sd-progress-fill" style="width: ${aiServer.cpu.percent}%"></div>
                                </div>
                                <div class="sd-metric-info">
                                    <span>${aiServer.cpu.percent}%</span>
                                    <span>${aiServer.cpu.count} cores (${aiServer.cpu.freq_current} MHz)</span>
                                </div>
                            </div>
                            
                            <div class="sd-metric">
                                <h4>메모리</h4>
                                <div class="sd-progress-bar">
                                    <div class="sd-progress-fill" style="width: ${aiServer.memory.percent}%"></div>
                                </div>
                                <div class="sd-metric-info">
                                    <span>${aiServer.memory.percent}%</span>
                                    <span>${aiServer.memory.used_gb} / ${aiServer.memory.total_gb} GB</span>
                                </div>
                            </div>
                            
                            <div class="sd-metric">
                                <h4>디스크</h4>
                                <div class="sd-progress-bar">
                                    <div class="sd-progress-fill" style="width: ${aiServer.disk.percent}%"></div>
                                </div>
                                <div class="sd-metric-info">
                                    <span>${aiServer.disk.percent}%</span>
                                    <span>${aiServer.disk.used_gb} / ${aiServer.disk.total_gb} GB</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="sd-server-network">
                            <h4>네트워크</h4>
                            <div class="sd-network-stats">
                                <div class="sd-network-item">
                                    <span class="sd-network-label">송신:</span>
                                    <span class="sd-network-value">${aiServer.network.bytes_sent_mb} MB</span>
                                </div>
                                <div class="sd-network-item">
                                    <span class="sd-network-label">수신:</span>
                                    <span class="sd-network-value">${aiServer.network.bytes_recv_mb} MB</span>
                                </div>
                            </div>
                        </div>
                        
                        ${aiServer.top_processes && aiServer.top_processes.length > 0 ? `
                            <div class="sd-server-processes">
                                <h4>주요 프로세스</h4>
                                <div class="sd-process-list">
                                    ${aiServer.top_processes.slice(0, 5).map(proc => `
                                        <div class="sd-process-item">
                                            <span class="sd-process-name">${window.sdUtils.escapeHtml(proc.name)}</span>
                                            <span class="sd-process-cpu">CPU: ${proc.cpu_percent}%</span>
                                            <span class="sd-process-memory">메모리: ${proc.memory_mb} MB</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        ${aiServer.gpu && aiServer.gpu.available ? `
                            <div class="sd-server-gpu">
                                <h4>Embedding GPU</h4>
                                <div class="sd-gpu-stats">
                                    ${aiServer.gpu.devices.map(gpu => `
                                        <div class="sd-gpu-device">
                                            <h5>${window.sdUtils.escapeHtml(gpu.name)} (GPU ${gpu.index})</h5>
                                            
                                            <div class="sd-metric">
                                                <div class="sd-metric-label">GPU 사용률</div>
                                                <div class="sd-progress-bar">
                                                    <div class="sd-progress-fill" style="width: ${gpu.utilization}%"></div>
                                                </div>
                                                <div class="sd-metric-info">
                                                    <span>${gpu.utilization}%</span>
                                                    ${gpu.temperature !== null ? `<span>온도: ${gpu.temperature}°C</span>` : ''}
                                                </div>
                                            </div>
                                            
                                            <div class="sd-metric">
                                                <div class="sd-metric-label">메모리</div>
                                                <div class="sd-progress-bar">
                                                    <div class="sd-progress-fill" style="width: ${gpu.memory.percent}%"></div>
                                                </div>
                                                <div class="sd-metric-info">
                                                    <span>${gpu.memory.percent}%</span>
                                                    <span>${gpu.memory.used} / ${gpu.memory.total} MB</span>
                                                </div>
                                            </div>
                                            
                                            ${gpu.power.draw !== null ? `
                                                <div class="sd-gpu-power">
                                                    <span class="sd-info-label">전력:</span>
                                                    <span class="sd-info-value">${gpu.power.draw}W / ${gpu.power.limit}W</span>
                                                </div>
                                            ` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : `
                            <div class="sd-server-gpu">
                                <h4>Embedding GPU</h4>
                                <div class="sd-no-gpu">
                                    <p>GPU가 감지되지 않았습니다</p>
                                </div>
                            </div>
                        `}
                        
                        ${gpuExternalHtml}
                    </div>
                `);
                $container.append($aiServerCard);
            }
            
            // Render WEB Server stats
            if (data.web_server) {
                const webServer = data.web_server;
                const $webServerCard = $(`
                    <div class="sd-server-card">
                        <div class="sd-server-header">
                            <h3>
                                <span class="sd-server-icon">🌐</span>
                                ${window.sdUtils.escapeHtml(webServer.name)}
                            </h3>
                            <span class="sd-server-status sd-status-${webServer.status}">
                                ${webServer.status === 'online' ? '🟢 온라인' : 
                                  webServer.status === 'offline' ? '🔴 오프라인' :
                                  webServer.status === 'timeout' ? '🟠 타임아웃' : '⚠️ 오류'}
                            </span>
                        </div>
                        
                        <div class="sd-server-info">
                            ${webServer.checked_url ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">URL:</span>
                                    <span class="sd-info-value">${window.sdUtils.escapeHtml(webServer.checked_url)}</span>
                                </div>
                            ` : ''}
                            ${webServer.hostname ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">호스트명:</span>
                                    <span class="sd-info-value">${window.sdUtils.escapeHtml(webServer.hostname)}</span>
                                </div>
                            ` : ''}
                            ${webServer.ip_address ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">IP 주소:</span>
                                    <span class="sd-info-value">${window.sdUtils.escapeHtml(webServer.ip_address)}</span>
                                </div>
                            ` : ''}
                            <div class="sd-info-row">
                                <span class="sd-info-label">운영체제:</span>
                                <span class="sd-info-value">DietPi</span>
                            </div>
                            ${webServer.os_info ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">Docker Base:</span>
                                    <span class="sd-info-value">${window.sdUtils.escapeHtml(webServer.os_info)}</span>
                                </div>
                            ` : ''}
                            ${webServer.uptime ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">가동 시간:</span>
                                    <span class="sd-info-value">${window.sdUtils.formatUptime(webServer.uptime)}</span>
                                </div>
                            ` : ''}
                            ${webServer.status_code ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">상태 코드:</span>
                                    <span class="sd-info-value">${webServer.status_code}</span>
                                </div>
                            ` : ''}
                            ${webServer.response_time !== null ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">응답 시간:</span>
                                    <span class="sd-info-value">${webServer.response_time} ms</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        ${webServer.cpu && webServer.memory && webServer.disk ? `
                            <div class="sd-server-metrics">
                                <div class="sd-metric">
                                    <h4>CPU</h4>
                                    <div class="sd-progress-bar">
                                        <div class="sd-progress-fill" style="width: ${webServer.cpu.percent}%"></div>
                                    </div>
                                    <div class="sd-metric-info">
                                        <span>${webServer.cpu.percent}%</span>
                                        ${webServer.cpu.count && webServer.cpu.freq_current ? 
                                            `<span>${webServer.cpu.count} cores (${webServer.cpu.freq_current} MHz)</span>` : 
                                            webServer.cpu.count ? `<span>${webServer.cpu.count} cores</span>` : ''
                                        }
                                    </div>
                                </div>
                                
                                <div class="sd-metric">
                                    <h4>메모리</h4>
                                    <div class="sd-progress-bar">
                                        <div class="sd-progress-fill" style="width: ${webServer.memory.percent}%"></div>
                                    </div>
                                    <div class="sd-metric-info">
                                        <span>${webServer.memory.percent}%</span>
                                        <span>${webServer.memory.used_gb} / ${webServer.memory.total_gb} GB</span>
                                    </div>
                                </div>
                                
                                <div class="sd-metric">
                                    <h4>디스크</h4>
                                    <div class="sd-progress-bar">
                                        <div class="sd-progress-fill" style="width: ${webServer.disk.percent}%"></div>
                                    </div>
                                    <div class="sd-metric-info">
                                        <span>${webServer.disk.percent}%</span>
                                        <span>${webServer.disk.used_gb} / ${webServer.disk.total_gb} GB</span>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${webServer.network ? `
                            <div class="sd-server-network">
                                <h4>네트워크</h4>
                                <div class="sd-network-stats">
                                    <div class="sd-network-item">
                                        <span class="sd-network-label">송신:</span>
                                        <span class="sd-network-value">${webServer.network.bytes_sent_mb} MB</span>
                                    </div>
                                    <div class="sd-network-item">
                                        <span class="sd-network-label">수신:</span>
                                        <span class="sd-network-value">${webServer.network.bytes_recv_mb} MB</span>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${webServer.top_processes && webServer.top_processes.length > 0 ? `
                            <div class="sd-server-processes">
                                <h4>주요 프로세스</h4>
                                <div class="sd-process-list">
                                    ${webServer.top_processes.slice(0, 5).map(proc => `
                                        <div class="sd-process-item">
                                            <span class="sd-process-name">${window.sdUtils.escapeHtml(proc.name)}</span>
                                            <span class="sd-process-cpu">CPU: ${proc.cpu_percent}%</span>
                                            <span class="sd-process-memory">메모리: ${proc.memory_mb} MB</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        ${webServer.error ? `
                            <div class="sd-server-error">
                                <h4>오류 정보</h4>
                                <div class="sd-error-content">
                                    <p>${window.sdUtils.escapeHtml(webServer.error)}</p>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `);
                $container.append($webServerCard);
            }
            
            // Render Vector Store stats
            if (data.vector_store) {
                const vectorStore = data.vector_store;
                const $vectorStoreCard = $(`
                    <div class="sd-server-card">
                        <div class="sd-server-header">
                            <h3>
                                <span class="sd-server-icon">🗄️</span>
                                ${window.sdUtils.escapeHtml(vectorStore.name)}
                            </h3>
                            <span class="sd-server-status sd-status-${vectorStore.status}">
                                ${vectorStore.status === 'online' ? '🟢 온라인' : '🔴 오프라인'}
                            </span>
                        </div>
                        
                        <div class="sd-server-info">
                            <div class="sd-info-row">
                                <span class="sd-info-label">타입:</span>
                                <span class="sd-info-value">${window.sdUtils.escapeHtml(vectorStore.type)}</span>
                            </div>
                            <div class="sd-info-row">
                                <span class="sd-info-label">고유 문서 수:</span>
                                <span class="sd-info-value">${window.sdUtils.formatNumber(vectorStore.unique_documents || vectorStore.document_count)}</span>
                            </div>
                            <div class="sd-info-row">
                                <span class="sd-info-label">전체 벡터 수:</span>
                                <span class="sd-info-value">${window.sdUtils.formatNumber(vectorStore.total_vectors)}</span>
                            </div>
                            ${vectorStore.collection ? `
                                <div class="sd-info-row">
                                    <span class="sd-info-label">컬렉션:</span>
                                    <span class="sd-info-value">${window.sdUtils.escapeHtml(vectorStore.collection)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `);
                $container.append($vectorStoreCard);
            }
        }
    };
    
})(jQuery);