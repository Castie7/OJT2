<template>
  <div class="approval-container">
    <h2>Pending Reviews</h2>

    <div v-if="loading" class="loading">Loading...</div>
    <div v-else-if="pendingItems.length === 0" class="empty-state">No pending items.</div>
    
    <div v-else class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Author</th>
            <th>File</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in pendingItems" :key="item.id">
            <td>{{ item.title }}</td>
            <td>{{ item.author }}</td>
            <td>
              <a v-if="item.file_path" :href="getFileUrl(item.file_path)" target="_blank" class="btn-link">View PDF</a>
            </td>
            <td>
              <button @click="openReviewModal(item)" class="btn btn-review">Review & Comment</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showModal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Review: {{ selectedItem.title }}</h3>
          <button @click="closeModal" class="close-btn">×</button>
        </div>
        
        <div class="modal-body">
          <div class="comments-section">
            <h4>Revision Notes / Comments</h4>
            <div class="comments-list" ref="chatList">
              <div v-for="c in comments" :key="c.id" :class="['comment-bubble', c.role === 'admin' ? 'admin-msg' : 'user-msg']">
                <strong>{{ c.user_name }} ({{ c.role }}):</strong>
                <p>{{ c.comment }}</p>
                <small>{{ formatDate(c.created_at) }}</small>
              </div>
              <p v-if="comments.length === 0" class="no-comments">No comments yet.</p>
            </div>
            
            <div class="comment-input">
              <textarea v-model="newComment" placeholder="Write a revision note..."></textarea>
              <button @click="postComment" class="btn btn-send">Send Note</button>
            </div>
          </div>

          <div class="modal-actions">
            <button @click="approveResearch(selectedItem.id)" class="btn btn-approve">Approve</button>
            <button @click="rejectResearch(selectedItem.id)" class="btn btn-reject">Reject</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios';

export default {
  props: ['currentUser'], 
  data() {
    return {
      pendingItems: [],
      loading: true,
      baseUrl: 'http://localhost:8080',
      
      // Modal State
      showModal: false,
      selectedItem: null,
      comments: [],
      newComment: ''
    };
  },
  mounted() {
    this.fetchPending();
  },
  methods: {
    getFileUrl(filename) { return `${this.baseUrl}/uploads/${filename}`; },
    formatDate(date) { return new Date(date).toLocaleString(); },

    // --- SECURITY HELPER: Get Token from Cookie ---
    getHeaders() {
      const token = document.cookie.split('; ').find(row => row.startsWith('auth_token='))?.split('=')[1];
      return { 
        headers: { 'Authorization': token } 
      };
    },

    // FETCH PENDING (Now Secured)
    async fetchPending() {
      try {
        // Pass headers to the GET request
        const res = await axios.get(`${this.baseUrl}/research/pending`, this.getHeaders());
        this.pendingItems = res.data;
      } catch (e) { 
        console.error(e); 
        // Optional: Alert if access denied
        if(e.response && e.response.status === 403) {
            alert("Access Denied: Admins Only");
        }
      } finally { 
        this.loading = false; 
      }
    },

    // OPEN MODAL
    async openReviewModal(item) {
      this.selectedItem = item;
      this.showModal = true;
      this.comments = [];
      try {
        // Fetch comments (Secured)
        const res = await axios.get(`${this.baseUrl}/research/comments/${item.id}`, this.getHeaders());
        this.comments = res.data;
      } catch (e) { console.error("Error loading comments", e); }
    },

    closeModal() {
      this.showModal = false;
      this.selectedItem = null;
      this.newComment = '';
    },

    // POST COMMENT (Now Secured)
    async postComment() {
      if (!this.newComment.trim()) return;

      try {
        await axios.post(`${this.baseUrl}/research/comment`, {
          research_id: this.selectedItem.id,
          user_id: this.currentUser.id, 
          user_name: this.currentUser.name || 'Admin', // Use dynamic name
          role: 'admin',
          comment: this.newComment
        }, this.getHeaders()); // <--- Add Headers here
        
        // Refresh comments
        this.newComment = '';
        const res = await axios.get(`${this.baseUrl}/research/comments/${this.selectedItem.id}`, this.getHeaders());
        this.comments = res.data;
      } catch (e) { alert("Failed to post comment"); }
    },

    // APPROVE (Now Secured)
    async approveResearch(id) {
      if(!confirm("Approve this research?")) return;
      try {
        // POST requests need headers as the 3rd argument (after data)
        await axios.post(`${this.baseUrl}/research/approve/${id}`, {}, this.getHeaders());
        this.fetchPending();
        this.closeModal();
      } catch (e) { alert("Failed to approve. Access Denied."); }
    },

    // REJECT (Now Secured)
    async rejectResearch(id) {
      if(!confirm("Reject this research?")) return;
      try {
        await axios.post(`${this.baseUrl}/research/reject/${id}`, {}, this.getHeaders());
        this.fetchPending();
        this.closeModal();
      } catch (e) { alert("Failed to reject. Access Denied."); }
    }
  }
};
</script>

<style scoped>
/* Basic Table Styles */
.approval-container { padding: 20px; max-width: 1000px; margin: 0 auto; }
.table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.table th, .table td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
.table th { background-color: #f8f9fa; font-weight: bold; }
.btn { padding: 8px 12px; cursor: pointer; border-radius: 4px; border: none; color: white; margin-right: 5px; transition: opacity 0.2s; }
.btn:hover { opacity: 0.9; }
.btn-review { background: #17a2b8; }
.btn-approve { background: #28a745; }
.btn-reject { background: #dc3545; }
.btn-send { background: #007bff; margin-top: 5px; }

/* Modal Styles */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000;}
.modal-content { background: white; width: 600px; padding: 20px; border-radius: 8px; max-height: 80vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; margin-bottom: 15px; align-items: center; }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; }

.comments-list { background: #f9f9f9; padding: 10px; height: 250px; overflow-y: auto; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 4px; }
.comment-bubble { padding: 10px; margin-bottom: 10px; border-radius: 6px; font-size: 0.9em; max-width: 85%; }
.admin-msg { background: #e2e6ea; text-align: right; border-left: 4px solid #17a2b8; margin-left: auto; }
.user-msg { background: #fff3cd; border-left: 4px solid #ffc107; }

.comment-input textarea { width: 100%; height: 60px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; resize: none; }
.modal-actions { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; text-align: right; }
</style>