<?php
/**
 * Group Lending & Solidarity Mechanism Service
 * Handles group management, member registration, and group-based lending
 */

class GroupLendingService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new lending group
     */
    public function createGroup($data) {
        try {
            $this->db->beginTransaction();
            
            $groupData = [
                'group_name' => $data['group_name'],
                'group_leader_id' => $data['group_leader_id'],
                'description' => $data['description'] ?? null,
                'group_status' => 'active'
            ];
            
            $group = $this->db->insert('lending_groups', $groupData);
            
            // Add group leader as first member
            $this->addGroupMember($group['group_id'], $data['group_leader_id']);
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'lending_groups',
                $group['group_id'],
                null,
                $groupData
            );
            
            $this->db->commit();
            return $group;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Group creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add a member to a group
     */
    public function addGroupMember($groupId, $clientId) {
        try {
            $group = $this->db->getById('lending_groups', $groupId, 'group_id');
            
            if (!$group) {
                throw new Exception('Group not found');
            }
            
            $memberData = [
                'group_id' => $groupId,
                'client_id' => $clientId,
                'member_status' => 'active'
            ];
            
            $member = $this->db->insert('group_members', $memberData);
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'group_members',
                $member['member_id'],
                null,
                $memberData
            );
            
            return $member;
        } catch (Exception $e) {
            log_message('ERROR', 'Adding group member failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove a member from a group
     */
    public function removeGroupMember($memberId) {
        try {
            $oldData = $this->db->getById('group_members', $memberId, 'member_id');
            
            $result = $this->db->update(
                'group_members',
                ['member_status' => 'removed'],
                'member_id = ?',
                [$memberId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'group_members',
                $memberId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'Removing group member failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get group details
     */
    public function getGroup($groupId) {
        return $this->db->getById('lending_groups', $groupId, 'group_id');
    }

    /**
     * Get all groups
     */
    public function getAllGroups($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT * FROM lending_groups ORDER BY group_id DESC LIMIT ? OFFSET ?";
        
        $groups = $this->db->fetchAll($query, [$perPage, $offset]);
        
        $total = $this->db->count('lending_groups');
        
        return [
            'groups' => $groups,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Get group members
     */
    public function getGroupMembers($groupId) {
        $query = "SELECT gm.*, c.first_name, c.last_name, c.email, c.contact_number
                  FROM group_members gm
                  JOIN clients c ON gm.client_id = c.client_id
                  WHERE gm.group_id = ? AND gm.member_status = 'active'
                  ORDER BY gm.join_date ASC";
        return $this->db->fetchAll($query, [$groupId]);
    }

    /**
     * Get client's groups
     */
    public function getClientGroups($clientId) {
        $query = "SELECT lg.*, COUNT(gm.member_id) as member_count
                  FROM lending_groups lg
                  JOIN group_members gm ON lg.group_id = gm.group_id
                  WHERE gm.client_id = ? AND gm.member_status = 'active'
                  GROUP BY lg.group_id
                  ORDER BY lg.group_id DESC";
        return $this->db->fetchAll($query, [$clientId]);
    }

    /**
     * Get group statistics
     */
    public function getGroupStats($groupId) {
        $query = "SELECT 
                    COUNT(DISTINCT gm.member_id) as total_members,
                    COUNT(DISTINCT CASE WHEN gm.member_status = 'active' THEN gm.member_id END) as active_members,
                    COUNT(DISTINCT l.loan_id) as total_loans,
                    COALESCE(SUM(l.loan_amount), 0) as total_loan_amount,
                    COALESCE(SUM(l.outstanding_balance), 0) as total_outstanding
                  FROM lending_groups g
                  LEFT JOIN group_members gm ON g.group_id = gm.group_id
                  LEFT JOIN loan l ON gm.client_id = l.client_id
                  WHERE g.group_id = ?";
        
        return $this->db->fetchOne($query, [$groupId]);
    }

    /**
     * Approve group loan (group guarantee)
     */
    public function approveGroupLoan($groupId, $loanId) {
        try {
            $this->db->beginTransaction();
            
            // Create group loan guarantee record (conceptual)
            // This would link the loan to the group for guarantee purposes
            
            log_message('INFO', "Group guarantee approved for loan $loanId in group $groupId");
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Group loan approval failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update group status
     */
    public function updateGroupStatus($groupId, $status) {
        try {
            $oldData = $this->db->getById('lending_groups', $groupId, 'group_id');
            
            $result = $this->db->update(
                'lending_groups',
                ['group_status' => $status],
                'group_id = ?',
                [$groupId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'lending_groups',
                $groupId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'Group status update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get group performance metrics
     */
    public function getGroupPerformanceMetrics($groupId) {
        $query = "SELECT 
                    g.group_name,
                    COUNT(DISTINCT gm.member_id) as member_count,
                    COUNT(DISTINCT l.loan_id) as loan_count,
                    COALESCE(SUM(l.loan_amount), 0) as total_loaned,
                    COALESCE(SUM(r.payment_amount), 0) as total_repaid,
                    COALESCE(SUM(l.outstanding_balance), 0) as outstanding_balance,
                    ROUND(COALESCE(SUM(r.payment_amount) / SUM(l.loan_amount) * 100, 0), 2) as repayment_rate
                  FROM lending_groups g
                  LEFT JOIN group_members gm ON g.group_id = gm.group_id
                  LEFT JOIN loan l ON gm.client_id = l.client_id
                  LEFT JOIN repayments r ON l.loan_id = r.loan_id
                  WHERE g.group_id = ?
                  GROUP BY g.group_id, g.group_name";
        
        return $this->db->fetchOne($query, [$groupId]);
    }
}
