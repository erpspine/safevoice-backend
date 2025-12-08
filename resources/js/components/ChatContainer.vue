<template>
    <div class="chat-container">
        <!-- Chat Header -->
        <div class="chat-header">
            <div class="case-info">
                <h3 class="case-title">Case Communication</h3>
                <p class="case-subtitle">Case #{{ caseToken }}</p>
            </div>
            <div class="chat-actions">
                <button
                    @click="refreshMessages"
                    class="refresh-btn"
                    :disabled="isLoading"
                >
                    <i
                        class="fas fa-sync-alt"
                        :class="{ 'animate-spin': isLoading }"
                    ></i>
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="messages-area" ref="messagesContainer">
            <div class="messages-list">
                <!-- Loading State -->
                <div v-if="isLoading" class="loading-state">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner animate-spin"></i>
                    </div>
                    <p>Loading messages...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="messages.length === 0" class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h4>No messages yet</h4>
                    <p>Start the conversation by sending a message below.</p>
                </div>

                <!-- Messages -->
                <div v-else class="messages-container">
                    <MessageCard
                        v-for="message in messages"
                        :key="message.id"
                        :message="message"
                        :current-user-type="currentUserType"
                        @reply="handleReply"
                        @markRead="markMessageAsRead"
                        @downloadAttachment="downloadAttachment"
                    />
                </div>
            </div>
        </div>

        <!-- Message Input Area -->
        <div class="message-input-area">
            <!-- Reply Context -->
            <div v-if="replyToMessage" class="reply-context-bar">
                <div class="reply-info">
                    <i class="fas fa-reply"></i>
                    <span
                        >Replying to:
                        {{ truncateMessage(replyToMessage.message, 50) }}</span
                    >
                </div>
                <button @click="cancelReply" class="cancel-reply-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Input Form -->
            <form @submit.prevent="sendMessage" class="message-form">
                <div class="input-row">
                    <!-- File Upload -->
                    <div class="file-upload-section">
                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.xlsx,.xls"
                            @change="handleFileUpload"
                            class="hidden"
                        />
                        <button
                            type="button"
                            @click="$refs.fileInput.click()"
                            class="file-upload-btn"
                            :class="{ 'has-files': selectedFiles.length > 0 }"
                        >
                            <i class="fas fa-paperclip"></i>
                            <span
                                v-if="selectedFiles.length > 0"
                                class="file-count"
                            >
                                {{ selectedFiles.length }}
                            </span>
                        </button>
                    </div>

                    <!-- Message Input -->
                    <div class="message-input-wrapper">
                        <textarea
                            v-model="messageText"
                            ref="messageInput"
                            placeholder="Type your message..."
                            class="message-input"
                            rows="1"
                            :disabled="isSending"
                            @keydown.enter.exact.prevent="sendMessage"
                            @keydown.enter.shift.exact="addNewLine"
                            @input="adjustTextareaHeight"
                        ></textarea>
                    </div>

                    <!-- Send Button -->
                    <button
                        type="submit"
                        class="send-btn"
                        :disabled="!canSendMessage || isSending"
                    >
                        <i
                            v-if="isSending"
                            class="fas fa-spinner animate-spin"
                        ></i>
                        <i v-else class="fas fa-paper-plane"></i>
                    </button>
                </div>

                <!-- Selected Files Display -->
                <div v-if="selectedFiles.length > 0" class="selected-files">
                    <div class="files-header">
                        <span
                            >Selected Files ({{ selectedFiles.length }}/5)</span
                        >
                        <button
                            type="button"
                            @click="clearFiles"
                            class="clear-files-btn"
                        >
                            Clear All
                        </button>
                    </div>
                    <div class="files-list">
                        <div
                            v-for="(file, index) in selectedFiles"
                            :key="index"
                            class="file-item"
                        >
                            <div class="file-icon">
                                <i :class="getFileIcon(file.type)"></i>
                            </div>
                            <div class="file-info">
                                <span class="file-name">{{ file.name }}</span>
                                <span class="file-size">{{
                                    formatFileSize(file.size)
                                }}</span>
                            </div>
                            <button
                                type="button"
                                @click="removeFile(index)"
                                class="remove-file-btn"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import MessageCard from "./MessageCard.vue";

export default {
    name: "ChatContainer",
    components: {
        MessageCard,
    },
    props: {
        caseId: {
            type: String,
            required: true,
        },
        caseToken: {
            type: String,
            required: true,
        },
        sessionToken: {
            type: String,
            required: true,
        },
        currentUserType: {
            type: String,
            default: "reporter",
        },
    },
    data() {
        return {
            messages: [],
            messageText: "",
            selectedFiles: [],
            replyToMessage: null,
            isLoading: false,
            isSending: false,
            error: null,
        };
    },
    computed: {
        canSendMessage() {
            return (
                this.messageText.trim().length > 0 ||
                this.selectedFiles.length > 0
            );
        },
    },
    mounted() {
        this.loadMessages();
    },
    methods: {
        async loadMessages() {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await fetch(
                    `/api/public/cases/${this.caseId}/messages`,
                    {
                        headers: {
                            Authorization: `Bearer ${this.sessionToken}`,
                            "Content-Type": "application/json",
                        },
                    }
                );

                if (!response.ok) {
                    throw new Error("Failed to load messages");
                }

                const data = await response.json();
                this.messages = data.data || [];

                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            } catch (error) {
                this.error = error.message;
                console.error("Error loading messages:", error);
            } finally {
                this.isLoading = false;
            }
        },

        async sendMessage() {
            if (!this.canSendMessage || this.isSending) return;

            this.isSending = true;

            try {
                const formData = new FormData();
                formData.append("message", this.messageText.trim());

                if (this.replyToMessage) {
                    formData.append(
                        "parent_message_id",
                        this.replyToMessage.id
                    );
                }

                // Add selected files
                this.selectedFiles.forEach((file) => {
                    formData.append("attachments[]", file);
                });

                const response = await fetch(
                    `/api/public/cases/${this.caseId}/messages`,
                    {
                        method: "POST",
                        headers: {
                            Authorization: `Bearer ${this.sessionToken}`,
                        },
                        body: formData,
                    }
                );

                if (!response.ok) {
                    throw new Error("Failed to send message");
                }

                // Clear form
                this.messageText = "";
                this.selectedFiles = [];
                this.replyToMessage = null;

                // Reload messages
                await this.loadMessages();
            } catch (error) {
                this.error = error.message;
                console.error("Error sending message:", error);
            } finally {
                this.isSending = false;
            }
        },

        refreshMessages() {
            this.loadMessages();
        },

        handleReply(message) {
            this.replyToMessage = message;
            this.$refs.messageInput.focus();
        },

        cancelReply() {
            this.replyToMessage = null;
        },

        async markMessageAsRead(messageId) {
            try {
                const response = await fetch(
                    `/api/public/cases/${this.caseId}/messages/read`,
                    {
                        method: "PUT",
                        headers: {
                            Authorization: `Bearer ${this.sessionToken}`,
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            message_ids: [messageId],
                        }),
                    }
                );

                if (response.ok) {
                    // Update local message state
                    const messageIndex = this.messages.findIndex(
                        (m) => m.id === messageId
                    );
                    if (messageIndex !== -1) {
                        this.messages[messageIndex].is_read = true;
                    }
                }
            } catch (error) {
                console.error("Error marking message as read:", error);
            }
        },

        downloadAttachment(attachment) {
            // Implement download functionality
            console.log("Download attachment:", attachment);
        },

        handleFileUpload(event) {
            const files = Array.from(event.target.files);
            const maxFiles = 5;

            if (this.selectedFiles.length + files.length > maxFiles) {
                alert(`You can only select up to ${maxFiles} files.`);
                return;
            }

            this.selectedFiles.push(...files);
        },

        removeFile(index) {
            this.selectedFiles.splice(index, 1);
        },

        clearFiles() {
            this.selectedFiles = [];
        },

        adjustTextareaHeight() {
            const textarea = this.$refs.messageInput;
            textarea.style.height = "auto";
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + "px";
        },

        addNewLine() {
            this.messageText += "\n";
            this.$nextTick(() => {
                this.adjustTextareaHeight();
            });
        },

        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        truncateMessage(message, maxLength) {
            if (message.length <= maxLength) return message;
            return message.substring(0, maxLength) + "...";
        },

        formatFileSize(bytes) {
            if (bytes === 0) return "0 Bytes";
            const k = 1024;
            const sizes = ["Bytes", "KB", "MB", "GB"];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (
                parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
            );
        },

        getFileIcon(mimeType) {
            if (mimeType.includes("image/"))
                return "fas fa-image text-blue-500";
            if (mimeType.includes("pdf")) return "fas fa-file-pdf text-red-500";
            if (mimeType.includes("word"))
                return "fas fa-file-word text-blue-600";
            if (mimeType.includes("excel") || mimeType.includes("spreadsheet"))
                return "fas fa-file-excel text-green-600";
            if (mimeType.includes("text/"))
                return "fas fa-file-alt text-gray-600";
            return "fas fa-file text-gray-500";
        },
    },
};
</script>

<style scoped>
.chat-container {
    @apply h-full flex flex-col bg-gray-50;
}

.chat-header {
    @apply flex items-center justify-between p-4 bg-white border-b shadow-sm;
}

.case-info {
    @apply flex-1;
}

.case-title {
    @apply text-lg font-semibold text-gray-800;
}

.case-subtitle {
    @apply text-sm text-gray-600;
}

.chat-actions {
    @apply flex items-center space-x-2;
}

.refresh-btn {
    @apply p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors;
}

.messages-area {
    @apply flex-1 overflow-hidden;
}

.messages-list {
    @apply h-full overflow-y-auto p-4;
}

.loading-state,
.empty-state {
    @apply flex flex-col items-center justify-center h-full text-gray-500;
}

.loading-spinner,
.empty-icon {
    @apply text-4xl mb-4;
}

.empty-icon {
    @apply text-gray-400;
}

.messages-container {
    @apply space-y-4;
}

.message-input-area {
    @apply bg-white border-t p-4;
}

.reply-context-bar {
    @apply flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg mb-3;
}

.reply-info {
    @apply flex items-center space-x-2 text-sm text-blue-800;
}

.cancel-reply-btn {
    @apply p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded;
}

.message-form {
    @apply space-y-3;
}

.input-row {
    @apply flex items-end space-x-3;
}

.file-upload-section {
    @apply flex-shrink-0;
}

.file-upload-btn {
    @apply relative p-3 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors border border-gray-200;
}

.file-upload-btn.has-files {
    @apply text-blue-600 bg-blue-50 border-blue-200;
}

.file-count {
    @apply absolute -top-2 -right-2 w-5 h-5 bg-blue-600 text-white text-xs rounded-full flex items-center justify-center;
}

.message-input-wrapper {
    @apply flex-1;
}

.message-input {
    @apply w-full p-3 border border-gray-200 rounded-lg resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent;
}

.send-btn {
    @apply flex-shrink-0 p-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors;
}

.selected-files {
    @apply border border-gray-200 rounded-lg p-3 bg-gray-50;
}

.files-header {
    @apply flex items-center justify-between mb-2;
}

.files-header span {
    @apply text-sm font-medium text-gray-700;
}

.clear-files-btn {
    @apply text-xs text-red-600 hover:text-red-800;
}

.files-list {
    @apply space-y-2;
}

.file-item {
    @apply flex items-center space-x-3 p-2 bg-white rounded border;
}

.file-icon {
    @apply text-lg;
}

.file-info {
    @apply flex-1 flex flex-col;
}

.file-name {
    @apply text-sm font-medium text-gray-800;
}

.file-size {
    @apply text-xs text-gray-500;
}

.remove-file-btn {
    @apply p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors;
}

/* Animations */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Responsive Design */
@media (max-width: 640px) {
    .chat-header {
        @apply p-3;
    }

    .messages-list {
        @apply p-2;
    }

    .message-input-area {
        @apply p-3;
    }
}
</style>
