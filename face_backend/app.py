from flask import Flask, request, jsonify
import face_recognition
import numpy as np
import cv2
import base64
import os
import re
from glob import glob
import pymysql
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

# Directory to store face encodings
ENCODINGS_DIR = 'face_backend/encodings'
os.makedirs(ENCODINGS_DIR, exist_ok=True)

def save_encoding(emp_id, encoding):
    np.save(os.path.join(ENCODINGS_DIR, f'{emp_id}.npy'), encoding)

def load_encoding(emp_id):
    path = os.path.join(ENCODINGS_DIR, f'{emp_id}.npy')
    if os.path.exists(path):
        return np.load(path)
    return None

def get_user_by_email(email):
    conn = pymysql.connect(host='localhost', user='root', password='', db='crm')
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT username, password FROM employee WHERE email=%s", (email,))
            row = cursor.fetchone()
            if row:
                return {'username': row[0], 'password': row[1]}
    finally:
        conn.close()
    return None

@app.route('/register_face', methods=['POST'])
def register_face():
    data = request.json
    emp_id = data.get('emp_id')
    img_data = data.get('image')
    if not emp_id or not img_data:
        return jsonify({'success': False, 'error': 'Missing emp_id or image'}), 400
    img_bytes = base64.b64decode(img_data.split(',')[-1])
    nparr = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    face_locations = face_recognition.face_locations(img)
    if not face_locations:
        return jsonify({'success': False, 'error': 'No face detected'}), 400
    face_encodings = face_recognition.face_encodings(img, face_locations)
    if not face_encodings:
        return jsonify({'success': False, 'error': 'No face encoding found'}), 400
    save_encoding(emp_id, face_encodings[0])
    return jsonify({'success': True})

@app.route('/verify_face', methods=['POST'])
def verify_face():
    data = request.json
    emp_id = data.get('emp_id')
    img_data = data.get('image')
    if not emp_id or not img_data:
        return jsonify({'success': False, 'error': 'Missing emp_id or image'}), 400

    # Use email as emp_id, sanitize for filename
    safe_email = re.sub(r'[^a-zA-Z0-9_.-]', '_', emp_id)
    stored_image_path = f'../backend/uploads/face_{safe_email}.jpg'
    if not os.path.exists(stored_image_path):
        return jsonify({'success': False, 'error': 'No stored face image found'}), 404

    # Load and encode stored image
    stored_img = face_recognition.load_image_file(stored_image_path)
    stored_encodings = face_recognition.face_encodings(stored_img)
    if not stored_encodings:
        return jsonify({'success': False, 'error': 'No face found in stored image'}), 400

    # Decode and encode new image
    import base64, numpy as np, cv2
    img_bytes = base64.b64decode(img_data.split(',')[-1])
    nparr = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    face_locations = face_recognition.face_locations(img)
    if not face_locations:
        return jsonify({'success': False, 'error': 'No face detected in webcam image'}), 400
    face_encodings = face_recognition.face_encodings(img, face_locations)
    if not face_encodings:
        return jsonify({'success': False, 'error': 'No face encoding found in webcam image'}), 400

    # Compare
    results = face_recognition.compare_faces([stored_encodings[0]], face_encodings[0])
    return jsonify({'success': bool(results[0])})

@app.route('/find_user_by_face', methods=['POST'])
def find_user_by_face():
    import base64, numpy as np, cv2, re
    from glob import glob
    data = request.json
    img_data = data.get('image')
    if not img_data:
        return jsonify({'success': False, 'error': 'No image provided'}), 400

    # Decode and encode new image
    img_bytes = base64.b64decode(img_data.split(',')[-1])
    nparr = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    face_locations = face_recognition.face_locations(img)
    if not face_locations:
        return jsonify({'success': False, 'error': 'No face detected in webcam image'}), 400
    face_encodings = face_recognition.face_encodings(img, face_locations)
    if not face_encodings:
        return jsonify({'success': False, 'error': 'No face encoding found in webcam image'}), 400

    # Find all unique users by email
    face_dir = '../backend/uploads/'
    user_files = glob(face_dir + 'face_*.jpg')
    user_emails = set()
    for f in user_files:
        m = re.match(r'.*face_(.*?)(?:_\d+)?\.jpg', f)
        if m:
            user_emails.add(m.group(1))

    for email in user_emails:
        # Find all images for this user
        user_imgs = glob(face_dir + f'face_{email}_*.jpg') or glob(face_dir + f'face_{email}.jpg')
        for img_path in user_imgs:
            stored_img = face_recognition.load_image_file(img_path)
            stored_encodings = face_recognition.face_encodings(stored_img)
            if not stored_encodings:
                continue
            results = face_recognition.compare_faces([stored_encodings[0]], face_encodings[0])
            if results[0]:
                user = get_user_by_email(email)
                if user:
                    return jsonify({'success': True, 'username': user['username'], 'password': user['password']})
                else:
                    return jsonify({'success': True, 'username': email, 'password': ''})
    return jsonify({'success': False, 'error': 'No matching face found'})

@app.route('/verify_login', methods=['POST', 'OPTIONS'])
def verify_login():
    if request.method == 'OPTIONS':
        # CORS preflight
        return '', 200
    data = request.json
    username = data.get('username')
    password = data.get('password')
    role = data.get('role')
    img_data = data.get('image')
    # 1. Check username and password
    conn = pymysql.connect(host='localhost', user='root', password='', db='crm')
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT email FROM employee WHERE username=%s AND password=%s", (username, password))
            row = cursor.fetchone()
            if not row:
                print("Invalid credentials")
                return jsonify({'success': False, 'error': 'Invalid credentials'})
            email = row[0]
    finally:
        conn.close()
    # After: email = row[0]
    import datetime
    today = datetime.date.today().isoformat()
    conn2 = pymysql.connect(host='localhost', user='root', password='', db='crm')
    try:
        with conn2.cursor() as cursor2:
            cursor2.execute(
                "SELECT id FROM leaves WHERE name=%s "
                "AND status='approved' AND %s BETWEEN startDate AND endDate",
                (username, today)
            )
            leave_row = cursor2.fetchone()
            if leave_row:
                return jsonify({'success': False, 'error': 'You are on leave today. Enjoy your day off! 😊'})
    finally:
        conn2.close()
    # 2. Check face match (use your existing face recognition logic, matching against face_{email}_*.jpg)
    face_dir = '../backend/uploads/'
    import base64, numpy as np, cv2, re
    from glob import glob
    img_bytes = base64.b64decode(img_data.split(',')[-1])
    nparr = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    face_locations = face_recognition.face_locations(img)
    if not face_locations:
        print("No face detected in webcam image")
        return jsonify({'success': False, 'error': 'No face detected in webcam image'}), 400
    face_encodings = face_recognition.face_encodings(img, face_locations)
    if not face_encodings:
        print("No face encoding found in webcam image")
        return jsonify({'success': False, 'error': 'No face encoding found in webcam image'}), 400
    safe_email = re.sub(r'[^a-zA-Z0-9_.-]', '_', email)
    user_imgs = glob(face_dir + f'face_{safe_email}_*.jpg') or glob(face_dir + f'face_{safe_email}.jpg')
    print(f"Checking {len(user_imgs)} stored images for {email}")
    for img_path in user_imgs:
        print(f"Comparing with {img_path}")
        stored_img = face_recognition.load_image_file(img_path)
        stored_encodings = face_recognition.face_encodings(stored_img)
        if not stored_encodings:
            print("No face found in stored image.")
            continue
        # Use a stricter tolerance
        results = face_recognition.compare_faces([stored_encodings[0]], face_encodings[0], tolerance=0.45)
        distance = face_recognition.face_distance([stored_encodings[0]], face_encodings[0])[0]
        print(f"Match result: {results[0]}, distance: {distance}")
        if results[0]:
            print("Face matched!")
            # Fetch employee id
            conn2 = pymysql.connect(host='localhost', user='root', password='', db='crm')
            try:
                with conn2.cursor() as cursor2:
                    cursor2.execute("SELECT id FROM employee WHERE email=%s", (email,))
                    row2 = cursor2.fetchone()
                    if row2:
                        user_id = row2[0]
                        return jsonify({'success': True, 'user_id': user_id})
            finally:
                conn2.close()
            return jsonify({'success': True})
    print("No face matched.")
    return jsonify({'success': False, 'error': 'Face does not match'})

if __name__ == '__main__':
    app.run(debug=True) 