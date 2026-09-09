#!/usr/bin/env python3
"""VNC keystroke sender to recover SSH access on jdp-claw."""
import socket
import struct
import time
import sys
import os
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
import warnings
warnings.filterwarnings("ignore")

# Read credentials from environment variables - never hardcode
HOST = os.environ.get("VNC_HOST", "")
PORT = int(os.environ.get("VNC_PORT", "0"))
VNC_PASSWORD = os.environ.get("VNC_PASSWORD", "")
ROOT_PASSWORD = os.environ.get("VNC_ROOT_PASSWORD", "")

if not all([HOST, PORT, VNC_PASSWORD]):
    print("ERROR: Set VNC_HOST, VNC_PORT, VNC_PASSWORD, VNC_ROOT_PASSWORD environment variables")
    sys.exit(1)

def char_to_keysym(c):
    return ord(c)

SPECIAL = {
    '\n': 0xff0d,
    '\t': 0xff09,
    '\x1b': 0xff1b,
}

def vnc_connect():
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(15)
    sock.connect((HOST, PORT))

    version = sock.recv(12)
    sock.sendall(b"RFB 003.008\n")

    count = sock.recv(1)[0]
    types = sock.recv(count)

    if 2 not in types:
        raise Exception("VNC auth not available")

    sock.sendall(bytes([2]))
    challenge = sock.recv(16)

    password = VNC_PASSWORD.ljust(8, '\x00')[:8]
    key = bytearray(password.encode('utf-8'))
    for i in range(len(key)):
        b = key[i]
        key[i] = ((b & 0x80) >> 7) | ((b & 0x40) >> 5) | ((b & 0x20) >> 3) | ((b & 0x10) >> 1) | \
                 ((b & 0x08) << 1) | ((b & 0x04) << 3) | ((b & 0x02) << 5) | ((b & 0x01) << 7)

    cipher = Cipher(algorithms.TripleDES(bytes(key) * 3), modes.ECB())
    encryptor = cipher.encryptor()
    response = encryptor.update(challenge) + encryptor.finalize()
    sock.sendall(response)

    result = struct.unpack("!I", sock.recv(4))[0]
    if result != 0:
        raise Exception("VNC auth failed")

    sock.sendall(bytes([1]))

    header = sock.recv(24)
    fb_width, fb_height = struct.unpack("!HH", header[:4])
    name_len = struct.unpack("!I", header[20:24])[0]
    if name_len > 0:
        sock.recv(name_len)

    print(f"Connected: {fb_width}x{fb_height}")
    return sock

def send_key(sock, keysym, down=True):
    msg = struct.pack("!BBHI", 4, 1 if down else 0, 0, keysym)
    sock.sendall(msg)

def type_string(sock, text, delay=0.05):
    for c in text:
        keysym = SPECIAL.get(c, char_to_keysym(c))
        send_key(sock, keysym, True)
        time.sleep(0.02)
        send_key(sock, keysym, False)
        time.sleep(delay)

def press_enter(sock):
    send_key(sock, 0xff0d, True)
    time.sleep(0.02)
    send_key(sock, 0xff0d, False)
    time.sleep(0.3)

def main():
    print("Connecting to VNC...")
    sock = vnc_connect()

    sock.sendall(struct.pack("!BBHH", 3, 0, 0, 0))

    print("Sending login sequence...")

    press_enter(sock)
    time.sleep(2)

    type_string(sock, "root")
    press_enter(sock)
    time.sleep(2)

    if ROOT_PASSWORD:
        type_string(sock, ROOT_PASSWORD)
        press_enter(sock)
        time.sleep(3)

    commands = [
        "sed -i 's/^#\\?PasswordAuthentication .*/PasswordAuthentication yes/' /etc/ssh/sshd_config",
        "sed -i 's/^#\\?PermitRootLogin .*/PermitRootLogin yes/' /etc/ssh/sshd_config",
        "systemctl restart ssh",
        "echo SSH_FIXED_OK",
    ]

    for cmd in commands:
        print(f"Sending: {cmd[:60]}...")
        type_string(sock, cmd, delay=0.03)
        press_enter(sock)
        time.sleep(1)

    time.sleep(3)

    type_string(sock, "echo FINAL_CHECK")
    press_enter(sock)
    time.sleep(2)

    print("All commands sent!")
    sock.close()

if __name__ == "__main__":
    main()
