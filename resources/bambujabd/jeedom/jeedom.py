# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
#
# Variante allégée du package jeedom officiel pour le plugin BambuJab :
# conserve uniquement jeedom_com (callback montant), jeedom_utils (logs/pid)
# et jeedom_socket (descendant PHP->démon). Pas de pyserial/pyudev (inutiles
# en LAN-only MQTT) pour limiter les dépendances.

import time
import logging
import os
import unicodedata
from threading import Thread
from collections.abc import Mapping
from queue import Queue
import socketserver
from socketserver import TCPServer, StreamRequestHandler

import requests


class jeedom_com():
    def __init__(self, apikey='', url='', cycle=0.5, retry=3):
        self._apikey = apikey
        self._url = url
        self._cycle = cycle
        self._retry = retry
        self._changes = {}
        if self._cycle > 0:
            Thread(target=self.__thread_changes_async, daemon=True).start()
        logging.info('jeedom.py: init request module v%s', requests.__version__)

    def __thread_changes_async(self):
        if self._cycle <= 0:
            return
        logging.info('jeedom.py: start changes async thread')
        while True:
            try:
                time.sleep(self._cycle)
                if len(self._changes) == 0:
                    continue
                changes = self._changes
                self._changes = {}
                self.__post_change(changes)
            except Exception as error:
                logging.error('jeedom.py: critical error on send_changes_async %s', error)

    def add_changes(self, key, value):
        if key.find('::') != -1:
            tmp_changes = {}
            changes = value
            for k in reversed(key.split('::')):
                if k not in tmp_changes:
                    tmp_changes[k] = {}
                tmp_changes[k] = changes
                changes = tmp_changes
                tmp_changes = {}
            if self._cycle <= 0:
                self.send_change_immediate(changes)
            else:
                self.merge_dict(self._changes, changes)
        else:
            if self._cycle <= 0:
                self.send_change_immediate({key: value})
            else:
                self._changes[key] = value

    def send_change_immediate(self, change):
        Thread(target=self.__post_change, args=(change,)).start()

    def __post_change(self, change):
        logging.debug('jeedom.py: send to jeedom: %s', change)
        for i in range(self._retry):
            try:
                r = requests.post(self._url + '?apikey=' + self._apikey, json=change, timeout=(0.5, 120), verify=False)
                if r.status_code == requests.codes.ok:
                    return True
                logging.warning('jeedom.py: error on send request to jeedom, return code %s', r.status_code)
            except Exception as error:
                logging.error('jeedom.py: error on send request to jeedom "%s" retry: %i/%i', error, i, self._retry)
            time.sleep(0.5)
        return False

    def merge_dict(self, d1, d2):
        for k, v2 in d2.items():
            v1 = d1.get(k)
            if isinstance(v1, Mapping) and isinstance(v2, Mapping):
                self.merge_dict(v1, v2)
            else:
                d1[k] = v2

    def test(self):
        try:
            response = requests.get(self._url + '?apikey=' + self._apikey, verify=False)
            if response.status_code != requests.codes.ok:
                logging.error('jeedom.py: callback error %s %s. Check Jeedom network configuration page',
                              response.status_code, response.reason)
                return False
        except Exception as e:
            logging.error('jeedom.py: callback unknown error: %s. Check Jeedom network configuration page', e)
            return False
        return True


class jeedom_utils():

    @staticmethod
    def convert_log_level(level='error'):
        # Accepte les libellés string ET les niveaux numériques Monolog renvoyés
        # par log::getLogLevel() côté PHP (100=debug, 200=info, 300=warning, 400=error).
        LEVELS = {
            'debug': logging.DEBUG,
            'info': logging.INFO,
            'notice': logging.WARNING,
            'warning': logging.WARNING,
            'error': logging.ERROR,
            'critical': logging.CRITICAL,
            'none': logging.CRITICAL,
            '100': logging.DEBUG,
            '200': logging.INFO,
            '250': logging.WARNING,
            '300': logging.WARNING,
            '400': logging.ERROR,
            '500': logging.CRITICAL,
        }
        return LEVELS.get(str(level), logging.CRITICAL)

    @staticmethod
    def set_log_level(level='error'):
        FORMAT = '[%(asctime)-15s][%(levelname)s] : %(message)s'
        logging.basicConfig(level=jeedom_utils.convert_log_level(level), format=FORMAT, datefmt="%Y-%m-%d %H:%M:%S")

    @staticmethod
    def stripped(s):
        return "".join([i for i in s if 32 <= ord(i) < 127])

    @staticmethod
    def write_pid(path):
        pid = str(os.getpid())
        logging.info("jeedom.py: writing PID %s to %s", pid, path)
        with open(path, 'w') as f:
            f.write("%s\n" % pid)

    @staticmethod
    def remove_accents(input_str):
        nkfd_form = unicodedata.normalize('NFKD', input_str)
        return u"".join([c for c in nkfd_form if not unicodedata.combining(c)])


JEEDOM_SOCKET_MESSAGE = Queue()


class jeedom_socket_handler(StreamRequestHandler):
    def handle(self):
        global JEEDOM_SOCKET_MESSAGE
        logging.debug("jeedom.py: client connected to [%s:%d]", self.client_address[0], self.client_address[1])
        lg = self.rfile.readline()
        JEEDOM_SOCKET_MESSAGE.put(lg)
        logging.debug("jeedom.py: message read from socket: %s", str(lg.strip()))


class jeedom_socket():

    def __init__(self, address='localhost', port=55000):
        self.address = address
        self.port = port
        socketserver.TCPServer.allow_reuse_address = True
        self.netAdapter = None

    def open(self):
        self.netAdapter = TCPServer((self.address, self.port), jeedom_socket_handler)
        if self.netAdapter:
            logging.info("jeedom.py: socket interface started")
            Thread(target=self.loopNetServer, daemon=True).start()
        else:
            logging.error("jeedom.py: cannot start socket interface")

    def loopNetServer(self):
        logging.info("jeedom.py: listening on [%s:%d]", self.address, self.port)
        self.netAdapter.serve_forever()
        logging.info("jeedom.py: loopNetServer thread stopped")

    def close(self):
        if self.netAdapter:
            self.netAdapter.shutdown()
