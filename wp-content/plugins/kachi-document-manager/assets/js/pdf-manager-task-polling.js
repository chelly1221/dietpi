/**
 * Task Polling for Decoupled File Processing
 * Polls the standalone file processor for task status
 */

(function($) {
    'use strict';

    window.PDFManagerTaskPolling = {
        pollingInterval: null,
        uploadedFileIds: new Set(),
        
        init: function() {
            console.log("📊 Task polling initialized");
        },
        
        // Called after successful simple upload
        startPollingForFiles: function(fileIds) {
            console.log("🔄 Starting task polling for files:", fileIds);
            
            // Add file IDs to polling set
            fileIds.forEach(id => this.uploadedFileIds.add(id));
            
            // Start polling if not already running
            if (!this.pollingInterval) {
                this.pollingInterval = setInterval(() => {
                    this.pollTaskStatus();
                }, 3000); // Poll every 3 seconds
            }
        },
        
        pollTaskStatus: async function() {
            if (this.uploadedFileIds.size === 0) {
                this.stopPolling();
                return;
            }
            
            try {
                const response = await window.PDFManagerAPI.request('processor-tasks/', {});
                const data = await response.json();
                
                if (data.tasks) {
                    this.updateTaskStatus(data.tasks);
                }
                
            } catch (error) {
                console.error("❌ Task polling error:", error);
            }
        },
        
        updateTaskStatus: function(tasks) {
            const completedTasks = [];
            
            tasks.forEach(task => {
                if (this.uploadedFileIds.has(task.file_id)) {
                    this.displayTaskProgress(task);
                    
                    // Remove completed/failed tasks from polling
                    if (task.status === 'completed' || task.status === 'failed') {
                        completedTasks.push(task.file_id);
                    }
                }
            });
            
            // Remove completed tasks
            completedTasks.forEach(fileId => {
                this.uploadedFileIds.delete(fileId);
                console.log(`✅ Stopped polling for completed task: ${fileId}`);
            });
            
            // Refresh document list if tasks completed
            if (completedTasks.length > 0 && window.PDFManagerDocuments) {
                window.PDFManagerDocuments.fetchStoredFiles(true); // Skip loading animation
            }
        },
        
        displayTaskProgress: function(task) {
            // Create or update progress display
            const progressContainer = document.getElementById('processing-tasks') || this.createProgressContainer();
            
            let taskElement = document.getElementById(`task-${task.file_id}`);
            if (!taskElement) {
                taskElement = this.createTaskElement(task);
                progressContainer.appendChild(taskElement);
            }
            
            // Update progress
            this.updateTaskElement(taskElement, task);
        },
        
        createProgressContainer: function() {
            const container = document.createElement('div');
            container.id = 'processing-tasks';
            container.className = 'processing-tasks-container';
            container.innerHTML = `
                <h4>처리 중인 작업</h4>
                <div class="tasks-list"></div>
            `;
            
            // Insert after upload form
            const uploadForm = document.querySelector('.file-upload-section');
            if (uploadForm) {
                uploadForm.parentNode.insertBefore(container, uploadForm.nextSibling);
            }
            
            return container;
        },
        
        createTaskElement: function(task) {
            const element = document.createElement('div');
            element.id = `task-${task.file_id}`;
            element.className = 'task-progress';
            
            return element;
        },
        
        updateTaskElement: function(element, task) {
            const statusClass = task.status === 'failed' ? 'failed' : 
                               task.status === 'completed' ? 'completed' : 'processing';
            
            element.className = `task-progress ${statusClass}`;
            element.innerHTML = `
                <div class="task-info">
                    <span class="filename">${task.filename}</span>
                    <span class="status">${this.getStatusText(task.status)}</span>
                </div>
                <div class="task-progress-bar">
                    <div class="progress-fill" style="width: ${task.progress}%"></div>
                </div>
                <div class="task-message">${task.message}</div>
                ${task.status === 'completed' || task.status === 'failed' ? 
                  '<button class="dismiss-task" onclick="PDFManagerTaskPolling.dismissTask(\'' + task.file_id + '\')">확인</button>' : ''}
            `;
        },
        
        getStatusText: function(status) {
            const statusMap = {
                'pending': '대기 중',
                'processing': '처리 중',
                'completed': '완료',
                'failed': '실패'
            };
            return statusMap[status] || status;
        },
        
        dismissTask: function(fileId) {
            const element = document.getElementById(`task-${fileId}`);
            if (element) {
                element.remove();
            }
            
            // Remove from polling set
            this.uploadedFileIds.delete(fileId);
            
            // Clean up empty container
            const container = document.getElementById('processing-tasks');
            const tasksList = container?.querySelector('.tasks-list');
            if (tasksList && tasksList.children.length === 0) {
                container.remove();
            }
        },
        
        stopPolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
                console.log("⏹️ Task polling stopped");
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        window.PDFManagerTaskPolling.init();
        
        // Add CSS for task progress display
        const style = document.createElement('style');
        style.textContent = `
            .processing-tasks-container {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 15px;
                margin: 20px 0;
            }
            
            .processing-tasks-container h4 {
                margin-top: 0;
                color: #495057;
            }
            
            .task-progress {
                background: white;
                border: 1px solid #e9ecef;
                border-radius: 6px;
                padding: 12px;
                margin-bottom: 10px;
            }
            
            .task-progress.processing {
                border-left: 4px solid #007bff;
            }
            
            .task-progress.completed {
                border-left: 4px solid #28a745;
            }
            
            .task-progress.failed {
                border-left: 4px solid #dc3545;
            }
            
            .task-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
            }
            
            .filename {
                font-weight: 500;
            }
            
            .status {
                font-size: 0.9em;
                color: #6c757d;
            }
            
            .task-progress-bar {
                height: 6px;
                background-color: #e9ecef;
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 8px;
            }
            
            .progress-fill {
                height: 100%;
                background-color: #007bff;
                transition: width 0.3s ease;
            }
            
            .task-progress.completed .progress-fill {
                background-color: #28a745;
            }
            
            .task-progress.failed .progress-fill {
                background-color: #dc3545;
            }
            
            .task-message {
                font-size: 0.85em;
                color: #6c757d;
                margin-bottom: 8px;
            }
            
            .dismiss-task {
                background: #6c757d;
                color: white;
                border: none;
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 0.8em;
                cursor: pointer;
            }
            
            .dismiss-task:hover {
                background: #5a6268;
            }
        `;
        document.head.appendChild(style);
    });

})(jQuery);