import React, { useState, useEffect, useRef, useCallback } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';
import { api } from '../services/apiService';
import { useAuth } from '../context/AuthContext';

/**
 * Doctor/patient chat.
 *
 * API shapes:
 *  GET  /chats                -> [{ ID, doctor_id, patient_id, last_message_at,
 *                                   patient_name | doctor_name, unread_count }]
 *  GET  /chats/{ID}/messages  -> { data: [{ ID, sender_id, message, created_at, ... }], meta }
 *                                (newest first)
 *  POST /chats/{ID}/messages  -> the new message
 *  PUT  /chats/{ID}/read
 * Chats store WordPress user IDs (not hm_doctors / hm_patients record IDs).
 */

const formatTime = (value) => {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

/** The other person in the chat (doctors see the patient, patients the doctor). */
const participantName = (chat) => chat.patient_name || chat.doctor_name || 'Conversation';

const Chat = () => {
  const { user } = useAuth();
  const myId = user ? Number(user.ID) : 0;

  const [chats, setChats] = useState([]);
  const [activeChat, setActiveChat] = useState(null);
  const [messages, setMessages] = useState([]);
  const [messageInput, setMessageInput] = useState('');
  const [loadingChats, setLoadingChats] = useState(true);
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const [messageError, setMessageError] = useState(null);
  const messagesEndRef = useRef(null);

  const fetchMessages = useCallback(async (chat) => {
    try {
      setLoadingMessages(true);
      setMessageError(null);
      const response = await api.get(`/chats/${chat.ID}/messages`);
      const list = Array.isArray(response.data && response.data.data) ? response.data.data : [];
      // The API returns newest first; show oldest at the top.
      setMessages([...list].reverse());
      api.put(`/chats/${chat.ID}/read`).catch(() => {});
      setChats((prev) => prev.map((c) => (c.ID === chat.ID ? { ...c, unread_count: 0 } : c)));
    } catch (err) {
      setMessages([]);
      setMessageError(
        err.response && err.response.status === 403
          ? 'You do not have access to this conversation.'
          : 'Failed to load messages.'
      );
    } finally {
      setLoadingMessages(false);
    }
  }, []);

  const fetchChats = useCallback(async () => {
    try {
      setLoadingChats(true);
      setError(null);
      const response = await api.get('/chats');
      const list = Array.isArray(response.data) ? response.data : [];
      setChats(list);
      if (list.length > 0) {
        setActiveChat(list[0]);
        fetchMessages(list[0]);
      }
    } catch (err) {
      setError('Failed to load conversations.');
    } finally {
      setLoadingChats(false);
    }
  }, [fetchMessages]);

  useEffect(() => {
    fetchChats();
  }, [fetchChats]);

  const sendMessage = async (e) => {
    e.preventDefault();
    const text = messageInput.trim();
    if (!text || !activeChat || sending) {
      return;
    }

    try {
      setSending(true);
      setMessageError(null);
      const response = await api.post(`/chats/${activeChat.ID}/messages`, { message: text });
      if (response.data && response.data.ID) {
        setMessages((prev) => [...prev, response.data]);
      }
      setMessageInput('');
    } catch (err) {
      setMessageError((err.response && err.response.data && err.response.data.message) || 'Failed to send message.');
    } finally {
      setSending(false);
    }
  };

  useEffect(() => {
    if (messagesEndRef.current && messagesEndRef.current.scrollIntoView) {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages]);

  if (loadingChats) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-4">
        <p>{error}</p>
        <Button variant="primary" className="mt-4" onClick={fetchChats}>
          Try Again
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Chat</h1>
      <p className="text-lg text-gray-600">Communication with doctors and staff</p>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        {/* Chat list */}
        <div className="md:col-span-1">
          <Card title="Conversations">
            <div className="divide-y divide-gray-200">
              {chats.length > 0 ? (
                chats.map((chat) => {
                  const name = participantName(chat);
                  const unread = Number(chat.unread_count) || 0;
                  return (
                    <div
                      key={chat.ID}
                      className={`p-3 cursor-pointer hover:bg-gray-50 ${activeChat && activeChat.ID === chat.ID ? 'bg-primary-50' : ''}`}
                      onClick={() => {
                        setActiveChat(chat);
                        fetchMessages(chat);
                      }}
                    >
                      <div className="flex items-center">
                        <div className="w-10 h-10 rounded-full bg-primary-600 text-white flex items-center justify-center mr-3">
                          {name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                          <div className="font-medium truncate">{name}</div>
                          <div className="text-xs text-gray-500">{formatTime(chat.last_message_at)}</div>
                        </div>
                        {unread > 0 && (
                          <div className="ml-auto bg-primary-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {unread}
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })
              ) : (
                <div className="p-4 text-center text-gray-500">No conversations yet</div>
              )}
            </div>
          </Card>
        </div>

        {/* Chat messages */}
        <div className="md:col-span-3">
          <Card>
            {activeChat ? (
              <>
                <div className="px-4 py-3 border-b border-gray-200">
                  <div className="font-medium">{participantName(activeChat)}</div>
                </div>

                <div className="p-4 h-96 overflow-y-auto">
                  {loadingMessages ? (
                    <div className="flex justify-center items-center h-full">
                      <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-primary-600"></div>
                    </div>
                  ) : messages.length > 0 ? (
                    <div className="space-y-4">
                      {messages.map((message) => {
                        const mine = Number(message.sender_id) === myId;
                        return (
                          <div key={message.ID} className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
                            <div
                              className={`max-w-xs md:max-w-md px-4 py-2 rounded-lg ${mine
                                ? 'bg-primary-600 text-white'
                                : 'bg-gray-100 text-gray-900'}`}
                            >
                              <div className="text-sm whitespace-pre-wrap break-words">{message.message}</div>
                              <div className={`text-xs mt-1 ${mine ? 'text-primary-100' : 'text-gray-500'}`}>
                                {formatTime(message.created_at)}
                              </div>
                            </div>
                          </div>
                        );
                      })}
                      <div ref={messagesEndRef} />
                    </div>
                  ) : (
                    <div className="flex justify-center items-center h-full text-gray-500">
                      No messages yet
                    </div>
                  )}
                </div>

                {messageError && (
                  <div className="px-4 py-2 text-sm text-red-700 bg-red-50 border-t border-red-200">{messageError}</div>
                )}

                <div className="px-4 py-3 border-t border-gray-200">
                  <form onSubmit={sendMessage} className="flex items-center">
                    <input
                      type="text"
                      className="flex-1 border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                      placeholder="Type your message..."
                      value={messageInput}
                      maxLength={5000}
                      onChange={(e) => setMessageInput(e.target.value)}
                    />
                    <Button type="submit" variant="primary" className="ml-2" disabled={sending || !messageInput.trim()}>
                      {sending ? 'Sending…' : 'Send'}
                    </Button>
                  </form>
                </div>
              </>
            ) : (
              <div className="p-6 text-center text-gray-500">
                Select a conversation
              </div>
            )}
          </Card>
        </div>
      </div>
    </div>
  );
};

export default Chat;
