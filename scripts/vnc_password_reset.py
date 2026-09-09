#!/usr/bin/env python3
"""VNC password reset - switch to TTY and reset root password."""
import socket
import struct
import time
import sys
import os
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
import warnings
warnings.filterwarnings("ignore")

HOST = os.environ.get("VNC_HOST", "")
PORT = int(os.environ.get("VNC_PORT", "0"))
VNC_PASSWORD = os.environ.get("VNC_PASSWORD", "")
NEW_ROOT_PASSWORD = os.environ.get("NEW_ROOT_PASSWORD", "")

if not all([HOST, PORT, VNC_PASSWORD, NEW_ROOT_PASSWORD]):
    print("ERROR: Set VNC_HOST, VNC_PORT, VNC_PASSWORD, NEW_ROOT_PASSWORD env vars")
    sys.exit(1)

SPECIAL = {
    '\n': 0xff0d, '\t': 0xff09, '\x1b': 0xff1b,
}

def vnc_connect():
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(15)
    sock.connect((HOST, PORT))
    sock.recv(12)
    sock.sendall(b"RFB 003.008\n")
    count = sock.recv(1)[0]
    types = sock.recv(count)
    if 2 not in types:
        raise Exception("No VNC auth")
    sock.sendall(bytes([2]))
    challenge = sock.recv(16)
    password = VNC_PASSWORD.ljust(8, '\x00')[:8]
    key = bytearray(password.encode('utf-8'))
    for i in range(len(key)):
        b = key[i]
        key[i] = ((b & 0x80) >> 7) | ((b & 0x40) >> 5) | ((b & 0x20) >> 3) | ((b & 0x10) >> 1) | \
                 ((b & 0x08) << 1) | ((b & 0x04) << 3) | ((b & 0x02) << 5) | ((b & 0x01) << 7)
    cipher = Cipher(algorithms.TripleDES(bytes(key) * 3), modes.ECB())
    enc = cipher.encryptor()
    sock.sendall(enc.update(challenge) + enc.finalize())
    result = struct.unpack("!I", sock.recv(4))[0]
    if result != 0:
        raise Exception("Auth failed")
    sock.sendall(bytes([1]))
    header = sock.recv(24)
    name_len = struct.unpack("!I", header[20:24])[0]
    if name_len > 0:
        sock.recv(name_len)
    print(f"Connected: {struct.unpack('!HH', header[:4])}")
    return sock

def key(sock, keysym, down=True):
    sock.sendall(struct.pack("!BBHI", 4, 1 if down else 0, 0, keysym))

def type_str(sock, text, delay=0.05):
    for c in text:
        ks = SPECIAL.get(c, ord(c))
        key(sock, ks, True)
        time.sleep(0.02)
        key(sock, ks, False)
        time.sleep(delay)

def enter(sock):
    key(sock, 0xff0d, True); time.sleep(0.02); key(sock, 0xff0d, False); time.sleep(0.3)

def ctrl_alt_f(sock, f_num):
    """Send Ctrl+Alt+F{n} to switch TTY"""
    ctrl = 0xffe3
    alt = 0xffe9
    f_keysym = 0xffbd + f_num - 1  # F1=0xffbd, F2=0xffbe, etc.
    key(sock, ctrl, True)
    key(sock, alt, True)
    key(sock, f_keysym, True)
    time.sleep(0.05)
    key(sock, f_keysym, False)
    key(sock, alt, False)
    key(sock, ctrl, False)
    time.sleep(0.5)

def main():
    print("Connecting...")
    sock = vnc_connect()
    # Keep alive with framebuffer request
    sock.sendall(struct.pack("!BBHH", 3, 0, 0, 0))

    # Switch to TTY3 (fresh login prompt)
    print("Switching to TTY3...")
    ctrl_alt_f(sock, 3)
    time.sleep(2)

    # Press Enter to get prompt
    enter(sock)
    time.sleep(1)

    # Login as root
    print("Logging in as root...")
    type_str(sock, "root")
    enter(sock)
    time.sleep(2)

    # Enter new password (if prompted) or just type commands
    # First let's check - type a harmless command
    type_str(sock, "whoami")
    enter(sock)
    time.sleep(2)

    # If we're logged in, change password
    print("Changing password...")
    type_str(sock, f"echo 'root:{NEW_ROOT_PASSWORD}' | chpasswd")
    enter(sock)
    time.sleep(2)

    # Enable SSH
    print("Enabling SSH...")
    type_str(sock, "sed -i 's/^#\\?PasswordAuthentication .*/PasswordAuthentication yes/' /etc/ssh/sshd_config")
    enter(sock)
    time.sleep(1)
    type_str(sock, "sed -i 's/^#\\?PermitRootLogin .*/PermitRootLogin yes/' /etc/ssh/sshd_config")
    enter(sock)
    time.sleep(1)
    type_str(sock, "systemctl restart ssh")
    enter(sock)
    time.sleep(2)

    # Verify
    type_str(sock, "echo DONE_ALL")
    enter(sock)
    time.sleep(2)

    print("All commands sent!")
    sock.close()

if __name__ == "__main__":
    main()
