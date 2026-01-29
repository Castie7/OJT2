<template>
  <div class="approval-container">
    <h2>Pending Research Approvals</h2>

    <div v-if="loading" class="loading">Loading pending items...</div>

    <div v-else-if="pendingItems.length === 0" class="empty-state">
      <p>No pending research items found.</p>
    </div>

    <div v-else class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Author</th>
            <th>Abstract</th>
            <th>File</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in pendingItems" :key="item.id">
            <td>{{ item.title }}</td>
            <td>{{ item.author }}</td>
            <td class="abstract-cell" :title="item.abstract">
              {{ item.abstract.length > 50 ? item.abstract.substring(0, 50) + '...' : item.abstract }}
            </td>
            <td>
              <a 
                v-if="item.file_path" 
                :href="getFileUrl(item.file_path)" 
                target="_blank" 
                class="btn-link"
              >
                View PDF
              </a>
              <span v-else class="text-muted">No File</span>
            </td>
            <td class="actions-cell">
              <button 
                @click="approveResearch(item.id)" 
                class="btn btn-approve"
                :disabled="processing === item.id"
              >
                {{ processing === item.id ? '...' : 'Approve' }}
              </button>
              
              <button 
                @click="rejectResearch(item.id)" 
                class="btn btn-reject"
                :disabled="processing === item.id"
              >
                Reject
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Approval',
  data() {
    return {
      pendingItems: [],
      loading: true,
      processing: null, // Stores the ID of the item currently being processed
      baseUrl: 'http://localhost:8080', // Change this to your CodeIgniter URL
    };
  },
  mounted() {
    this.fetchPending();
  },
  methods: {
    // Helper to get full file URL
    getFileUrl(filename) {
      return `${this.baseUrl}/uploads/${filename}`;
    },

    // Fetch Data
    async fetchPending() {
      try {
        const response = await axios.get(`${this.baseUrl}/research/pending`);
        this.pendingItems = response.data;
      } catch (error) {
        console.error("Error fetching pending items:", error);
        alert("Failed to load pending items.");
      } finally {
        this.loading = false;
      }
    },

    // Approve Action
    async approveResearch(id) {
      if (!confirm("Are you sure you want to approve this research? It will become public.")) return;

      this.processing = id;
      try {
        const response = await axios.post(`${this.baseUrl}/research/approve/${id}`);
        if (response.data.status === 'success') {
          // Remove item from list locally
          this.pendingItems = this.pendingItems.filter(item => item.id !== id);
          alert("Research Approved Successfully!");
        }
      } catch (error) {
        console.error("Error approving:", error);
        alert("Failed to approve research.");
      } finally {
        this.processing = null;
      }
    },

    // Reject Action
    async rejectResearch(id) {
      if (!confirm("Are you sure you want to reject this research?")) return;

      this.processing = id;
      try {
        const response = await axios.post(`${this.baseUrl}/research/reject/${id}`);
        if (response.data.status === 'success') {
          // Remove item from list locally
          this.pendingItems = this.pendingItems.filter(item => item.id !== id);
          alert("Research Rejected.");
        }
      } catch (error) {
        console.error("Error rejecting:", error);
        alert("Failed to reject research.");
      } finally {
        this.processing = null;
      }
    }
  }
};
</script>

<style scoped>
.approval-container {
  padding: 20px;
  max-width: 1000px;
  margin: 0 auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.table th, .table td {
  padding: 12px 15px;
  border-bottom: 1px solid #ddd;
  text-align: left;
}

.table th {
  background-color: #f8f9fa;
  font-weight: bold;
}

.abstract-cell {
  max-width: 250px;
  color: #555;
}

.btn {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  margin-right: 5px;
  font-size: 0.9em;
  transition: background 0.3s;
}

.btn-approve {
  background-color: #28a745;
  color: white;
}
.btn-approve:hover { background-color: #218838; }

.btn-reject {
  background-color: #dc3545;
  color: white;
}
.btn-reject:hover { background-color: #c82333; }

.btn-link {
  color: #007bff;
  text-decoration: none;
}
.btn-link:hover { text-decoration: underline; }

.loading, .empty-state {
  text-align: center;
  margin-top: 40px;
  color: #666;
  font-size: 1.2em;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>