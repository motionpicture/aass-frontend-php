<?php
namespace Aass\Frontend\Models;

class Media extends \Aass\Common\Models\Media
{
    public function getById($id)
    {
        $statement = $this->db->prepare('SELECT * FROM media WHERE id = :id');
        $statement->execute([
            ':id' => $id,
        ]);

        return $statement->fetch();
    }

    public function deleteById($id)
    {
        $statement = $this->db->prepare('UPDATE media SET status = :status, deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $result = $statement->execute([
            ':id' => $id,
            ':status' => self::STATUS_DELETED
        ]);

        return $result;
    }

    /**
     * 更新する
     *
     * @return boolean
     */
    public function update($params)
    {
        $this->logger->addDebug(var_export($params, true));
        if (isset($params['id']) && $params['id']) {
            $statement = $this->db->prepare('UPDATE media SET title = :title, description = :description, uploaded_by = :uploadedBy, updated_at = NOW() WHERE id = :id');
            $result = $statement->execute([
                ':id' => $params['id'],
                ':title' => $params['title'],
                ':description' => $params['description'],
                ':uploadedBy' => $params['uploaded_by']
            ]);
        } else {
            $statement = $this->db->prepare('INSERT INTO media (event_id, title, description, uploaded_by, status, filename, size, extension, playtime_string, playtime_seconds, asset_id, created_at, updated_at)'
                                          . ' VALUES (:eventId, :title, :description, :uploadedBy, :status, :filename, :size, :extension, :playtimeString, :playtimeSeconds, :assetId, NOW(), NOW())');
            $result = $statement->execute([
                ':eventId' => $params['event_id'],
                ':title' => $params['title'],
                ':description' => $params['description'],
                ':uploadedBy' => $params['uploaded_by'],
                ':status' => self::STATUS_ASSET_CREATED,
                ':filename' => $params['filename'],
                ':size' => $params['size'],
                ':extension' => $params['extension'],
                ':playtimeString' => null,
                ':playtimeSeconds' => null,
                ':assetId' => $params['asset_id'],
            ]);
        }

        return $result;
    }
}