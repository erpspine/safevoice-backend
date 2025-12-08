<template>
    <div class="message-wrapper" :class="messageWrapperClass">
        <div class="message-card" :class="messageCardClass">
            <!-- Message Header -->
            <div class="message-header">
                <div class="sender-info">
                    <div class="sender-avatar" :class="avatarClass">
                        <i :class="senderIcon"></i>
                    </div>
                    <div class="sender-details">
                        <span class="sender-name">{{ senderName }}</span>
                        <span class="message-time">{{
                            formatTime(message.created_at)
                        }}</span>
                    </div>
                </div>
                <div class="message-status">
                    <span
                        v-if="message.priority !== 'normal'"
                        class="priority-badge"
                        :class="`priority-${message.priority}`"
                    >
                        {{ message.priority.toUpperCase() }}
                    </span>
                    <span
                        v-if="isFromReporter && !message.is_read"
                        class="unread-indicator"
                    >
                        <i class="fas fa-circle"></i>
                    </span>
                </div>
            </div>

            <!-- Message Content -->
            <div class="message-content">
                <!-- Reply Context -->
                <div v-if="message.parent_message_id" class="reply-context">
                    <i class="fas fa-reply"></i>
                    <span>Replying to previous message</span>
                </div>

                <!-- Message Text -->
                <div class="message-text">
                    {{ message.message }}
                </div>

                <!-- Attachments -->
                <div
                    v-if="message.has_attachments && message.attachments"
                    class="attachments-section"
                >
                    <div class="attachments-header">
                        <i class="fas fa-paperclip"></i>
                        <span
                            >{{ message.attachments.length }} Attachment{{
                                message.attachments.length > 1 ? "s" : ""
                            }}</span
                        >
                    </div>
                    <div class="attachments-list">
                        <div
                            v-for="(attachment, index) in message.attachments"
                            :key="index"
                            class="attachment-item"
                        >
                            <div class="attachment-icon">
                                <i
                                    :class="getFileIcon(attachment.mime_type)"
                                ></i>
                            </div>
                            <div class="attachment-info">
                                <span class="attachment-name">{{
                                    attachment.original_name
                                }}</span>
                                <span class="attachment-size">{{
                                    formatFileSize(attachment.size)
                                }}</span>
                            </div>
                            <button
                                class="download-btn"
                                @click="downloadAttachment(attachment)"
                            >
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Footer -->
            <div class="message-footer">
                <div class="message-actions">
                    <button
                        class="action-btn reply-btn"
                        @click="$emit('reply', message)"
                    >
                        <i class="fas fa-reply"></i>
                        Reply
                    </button>
                    <button
                        v-if="!message.is_read && !isFromCurrentUser"
                        class="action-btn mark-read-btn"
                        @click="$emit('markRead', message.id)"
                    >
                        <i class="fas fa-check"></i>
                        Mark Read
                    </button>
                </div>
                <div class="message-metadata">
                    <span class="message-type">{{
                        formatMessageType(message.message_type)
                    }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "MessageCard",
    props: {
        message: {
            type: Object,
            required: true,
        },
        currentUserType: {
            type: String,
            default: "reporter", // 'reporter' or 'investigator'
        },
    },
    computed: {
        isFromReporter() {
            return this.message.sender_type === "reporter";
        },
        isFromCurrentUser() {
            return this.message.sender_type === this.currentUserType;
        },
        messageWrapperClass() {
            return {
                "message-from-me": this.isFromCurrentUser,
                "message-from-other": !this.isFromCurrentUser,
            };
        },
        messageCardClass() {
            return {
                "reporter-message": this.isFromReporter,
                "investigator-message":
                    this.message.sender_type === "investigator",
                "system-message": this.message.sender_type === "system",
                "high-priority":
                    this.message.priority === "high" ||
                    this.message.priority === "urgent",
                unread: !this.message.is_read && !this.isFromCurrentUser,
            };
        },
        avatarClass() {
            return {
                "reporter-avatar": this.isFromReporter,
                "investigator-avatar":
                    this.message.sender_type === "investigator",
                "system-avatar": this.message.sender_type === "system",
            };
        },
        senderIcon() {
            switch (this.message.sender_type) {
                case "reporter":
                    return "fas fa-user";
                case "investigator":
                    return "fas fa-user-tie";
                case "system":
                    return "fas fa-cog";
                default:
                    return "fas fa-user";
            }
        },
        senderName() {
            switch (this.message.sender_type) {
                case "reporter":
                    return "You";
                case "investigator":
                    return this.message.metadata?.sender_name || "Investigator";
                case "system":
                    return "System";
                default:
                    return "Unknown";
            }
        },
    },
    methods: {
        formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffInHours = Math.abs(now - date) / 36e5;

            if (diffInHours < 24) {
                return date.toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                });
            } else if (diffInHours < 168) {
                // 7 days
                return date.toLocaleDateString([], {
                    weekday: "short",
                    hour: "2-digit",
                    minute: "2-digit",
                });
            } else {
                return date.toLocaleDateString([], {
                    month: "short",
                    day: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                });
            }
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
        formatMessageType(type) {
            const types = {
                comment: "Comment",
                update: "Update",
                notification: "Notification",
                status_change: "Status Change",
                assignment: "Assignment",
            };
            return types[type] || "Message";
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
        downloadAttachment(attachment) {
            // Implement download logic
            this.$emit("downloadAttachment", attachment);
        },
    },
};
</script>

<style scoped>
.message-wrapper {
    @apply mb-4 flex;
}

.message-from-me {
    @apply justify-end;
}

.message-from-other {
    @apply justify-start;
}

.message-card {
    @apply max-w-2xl rounded-xl shadow-lg border transition-all duration-200 hover:shadow-xl;
    background: linear-gradient(145deg, #ffffff, #f8fafc);
}

.reporter-message {
    @apply border-blue-200 bg-gradient-to-br from-blue-50 to-white;
}

.investigator-message {
    @apply border-green-200 bg-gradient-to-br from-green-50 to-white;
}

.system-message {
    @apply border-gray-200 bg-gradient-to-br from-gray-50 to-white;
}

.high-priority {
    @apply border-red-300 bg-gradient-to-br from-red-50 to-white;
    box-shadow: 0 4px 20px rgba(239, 68, 68, 0.15);
}

.unread {
    @apply border-l-4 border-l-blue-500;
}

.message-header {
    @apply flex items-center justify-between p-4 pb-2;
}

.sender-info {
    @apply flex items-center space-x-3;
}

.sender-avatar {
    @apply w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold shadow-md;
}

.reporter-avatar {
    @apply bg-gradient-to-br from-blue-500 to-blue-600;
}

.investigator-avatar {
    @apply bg-gradient-to-br from-green-500 to-green-600;
}

.system-avatar {
    @apply bg-gradient-to-br from-gray-500 to-gray-600;
}

.sender-details {
    @apply flex flex-col;
}

.sender-name {
    @apply font-semibold text-gray-800 text-sm;
}

.message-time {
    @apply text-xs text-gray-500;
}

.message-status {
    @apply flex items-center space-x-2;
}

.priority-badge {
    @apply px-2 py-1 rounded-full text-xs font-bold uppercase;
}

.priority-high {
    @apply bg-orange-100 text-orange-800;
}

.priority-urgent {
    @apply bg-red-100 text-red-800;
}

.unread-indicator {
    @apply text-blue-500 text-xs;
}

.message-content {
    @apply px-4 pb-2;
}

.reply-context {
    @apply flex items-center space-x-2 text-sm text-gray-600 mb-2 p-2 bg-gray-50 rounded-lg border-l-2 border-gray-300;
}

.message-text {
    @apply text-gray-800 leading-relaxed whitespace-pre-wrap mb-3;
}

.attachments-section {
    @apply mt-3 p-3 bg-gray-50 rounded-lg border;
}

.attachments-header {
    @apply flex items-center space-x-2 text-sm font-semibold text-gray-700 mb-3;
}

.attachments-list {
    @apply space-y-2;
}

.attachment-item {
    @apply flex items-center space-x-3 p-2 bg-white rounded-lg border hover:bg-gray-50 transition-colors;
}

.attachment-icon {
    @apply text-lg;
}

.attachment-info {
    @apply flex-1 flex flex-col;
}

.attachment-name {
    @apply font-medium text-gray-800 text-sm;
}

.attachment-size {
    @apply text-xs text-gray-500;
}

.download-btn {
    @apply p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors;
}

.message-footer {
    @apply flex items-center justify-between px-4 pb-4;
}

.message-actions {
    @apply flex items-center space-x-2;
}

.action-btn {
    @apply flex items-center space-x-1 px-3 py-1 text-xs font-medium rounded-lg transition-colors;
}

.reply-btn {
    @apply text-gray-600 hover:text-blue-600 hover:bg-blue-50;
}

.mark-read-btn {
    @apply text-gray-600 hover:text-green-600 hover:bg-green-50;
}

.message-metadata {
    @apply text-xs text-gray-500;
}

.message-type {
    @apply font-medium;
}

/* Responsive Design */
@media (max-width: 640px) {
    .message-card {
        @apply max-w-full mx-2;
    }

    .message-header {
        @apply p-3 pb-2;
    }

    .message-content {
        @apply px-3;
    }

    .message-footer {
        @apply px-3 pb-3;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .message-card {
        background: linear-gradient(145deg, #374151, #4b5563);
    }

    .reporter-message {
        @apply border-blue-600 bg-gradient-to-br from-blue-900 to-blue-800;
    }

    .investigator-message {
        @apply border-green-600 bg-gradient-to-br from-green-900 to-green-800;
    }

    .system-message {
        @apply border-gray-600 bg-gradient-to-br from-gray-800 to-gray-700;
    }

    .sender-name {
        @apply text-gray-200;
    }

    .message-text {
        @apply text-gray-200;
    }

    .attachments-section {
        @apply bg-gray-700;
    }

    .attachment-item {
        @apply bg-gray-600 hover:bg-gray-500;
    }
}
</style>
