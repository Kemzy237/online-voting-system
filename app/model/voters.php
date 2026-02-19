<?php
function get_all_voters($conn){
    $sql = "SELECT * FROM voters";
    $stmt = $conn->prepare($sql);
    $stmt->execute([]);

    if($stmt->rowCount() > 0){
        $voters = $stmt->fetchAll();
    }else $voters = 0;

    return $voters;
}

function get_all_verified_voters($conn){
    $sql = "SELECT * FROM voters WHERE status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute(["verified"]);

    if($stmt->rowCount() > 0){
        $voters = $stmt->fetchAll();
    }else $voters = 0;

    return $voters;
}

function get_all_pending_voters($conn){
    $sql = "SELECT * FROM voters WHERE status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute(["pending"]);

    if($stmt->rowCount() > 0){
        $voters = $stmt->fetchAll();
    }else $voters = 0;

    return $voters;
}

function get_all_suspended_voters($conn){
    $sql = "SELECT * FROM voters WHERE status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute(["suspended"]);

    if($stmt->rowCount() > 0){
        $voters = $stmt->fetchAll();
    }else $voters = 0;

    return $voters;
}

function get_voter_by_email($conn, $email){
    $sql = "SELECT * FROM voters WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$email]);

    if($stmt->rowCount() > 0){
        return true;
    }else {
        return false;
    }
}

function insert_voter($conn, $data){
    $sql = "INSERT INTO voters (full_name, email, dob, contact, address, status, password) VALUES(?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($data);
    return true;
}

function get_voter_by_id($conn, $id){
    $sql = "SELECT * FROM voters WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);

    if($stmt->rowCount() == 1){
        $voter = $stmt->fetch();
    }else $voter = 0;

    return $voter;
}

function verify_voter_email($conn, $data)
{
    $sql = "SELECT * FROM voters WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($data);

    if ($stmt->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function update_voter_with_password($conn, $data){
    $sql = "UPDATE voters set full_name=?, email=?, contact=?, dob=?, address=?, status=?, password=? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($data);
}

function update_voter_without_password($conn, $data){
    $sql = "UPDATE voters set full_name=?, email=?, contact=?, dob=?, address=?, status=? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($data);
}

function delete_voter($conn, $id){
    $sql = "DELETE FROM voters WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
}

function get_voter_name_by_id($conn, $voter_id)
{
    $sql = "SELECT full_name FROM voters WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$voter_id]);

    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch();
        return $result['full_name'];
    } else {
        return null;
    }
}